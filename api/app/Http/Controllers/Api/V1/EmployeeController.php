<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetEmployeePasswordRequest;
use App\Http\Requests\SetEmployeeActiveRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\SyncEmployeeShiftsRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Mail\EmployeePasswordChangedMail;
use App\Mail\EmployeeWelcomeMail;
use App\Models\AppRelease;
use App\Models\TrackingSession;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\ShiftWindowResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class EmployeeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $employees = User::query()
            ->employees()
            ->with(['activeDevice', 'employeeShifts.template'])
            ->orderBy('name')
            ->get();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $plainPassword = $request->validated('password');

        $employee = DB::transaction(function () use ($request): User {
            $employee = User::create([
                ...$request->safe()->except(['is_active', 'shift_template_ids']),
                'role' => UserRole::Employee,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $employee->employeeShifts()->createMany(
                array_map(
                    fn (int $templateId) => ['template_id' => $templateId, 'effective_from' => now()],
                    $request->validated('shift_template_ids') ?? [],
                ),
            );

            return $employee->load('employeeShifts.template');
        });

        $latestRelease = AppRelease::query()->orderByDesc('version_code')->first();
        Mail::to($employee->email)->queue(new EmployeeWelcomeMail($employee, $plainPassword, $latestRelease));

        return EmployeeResource::make($employee)->response()->setStatusCode(201);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): EmployeeResource
    {
        $employee->update($request->validated());

        return EmployeeResource::make($employee->load('employeeShifts.template', 'activeDevice'));
    }

    public function syncShifts(SyncEmployeeShiftsRequest $request, User $employee): JsonResponse
    {
        $templateIds = $request->validated('shift_template_ids');

        DB::transaction(function () use ($employee, $templateIds): void {
            $employee->employeeShifts()->delete();
            $employee->employeeShifts()->createMany(
                array_map(fn (int $templateId) => ['template_id' => $templateId, 'effective_from' => now()], $templateIds),
            );
        });

        return response()->json(
            $employee->employeeShifts()->with('template')->orderBy('template_id')->get(),
        );
    }

    public function setActive(SetEmployeeActiveRequest $request, User $employee): EmployeeResource
    {
        $employee->update(['is_active' => $request->boolean('is_active')]);

        if (! $employee->is_active) {
            $employee->tokens()->delete();
        }

        return EmployeeResource::make($employee);
    }

    public function resetPassword(ResetEmployeePasswordRequest $request, User $employee): Response
    {
        $plainPassword = $request->validated('password');

        $employee->update(['password' => $plainPassword]);
        $employee->tokens()->delete();

        if ($employee->email !== null) {
            Mail::to($employee->email)->queue(new EmployeePasswordChangedMail($employee, $plainPassword));
        }

        return response()->noContent();
    }

    public function revokeDevice(User $employee, DeviceService $devices): Response
    {
        if ($employee->activeDevice !== null) {
            $devices->revoke($employee->activeDevice);
        }

        return response()->noContent();
    }

    public function destroy(User $employee): Response
    {
        abort_unless($employee->role === UserRole::Employee, 404);

        $suffix = hash('crc32b', (string) now()->getTimestampMs()).'_parsa';

        $employee->tokens()->delete();
        $employee->employeeShifts()->delete();
        $employee->update([
            'is_active' => false,
            'username' => $suffix,
            'phone' => $employee->phone === null ? null : "{$employee->phone}_{$suffix}",
            'email' => $employee->email === null ? null : "{$suffix}_{$employee->email}",
        ]);
        $employee->delete();

        return response()->noContent();
    }

    public function window(Request $request, User $employee, ShiftWindowResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $window = $resolver->resolveForDate($employee, $validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'window' => $window?->toApiArray(),
        ]);
    }

    public function session(User $employee): JsonResponse
    {
        $session = TrackingSession::where('employee_id', $employee->id)->whereNull('ended_at')->first();

        return response()->json([
            'started_at' => $session?->started_at?->toISOString(),
        ]);
    }
}
