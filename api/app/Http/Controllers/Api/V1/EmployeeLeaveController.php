<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeLeaveRequest;
use App\Http\Resources\EmployeeLeaveResource;
use App\Models\EmployeeLeave;
use App\Models\User;
use App\Services\ScheduleChangeLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmployeeLeaveController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(private readonly ScheduleChangeLogger $logger) {}

    public function index(Request $request, User $employee): AnonymousResourceCollection
    {
        abort_unless($employee->role === UserRole::Employee, 404);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $leaves = EmployeeLeave::where('employee_id', $employee->id)
            ->with('creator')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? self::PER_PAGE);

        return EmployeeLeaveResource::collection($leaves);
    }

    public function store(StoreEmployeeLeaveRequest $request, User $employee): JsonResponse
    {
        abort_unless($employee->role === UserRole::Employee, 404);

        $leave = EmployeeLeave::create([
            'employee_id' => $employee->id,
            'starts_at' => $request->startsAt(),
            'ends_at' => $request->endsAt(),
            'note' => $request->validated('note'),
            'created_by' => $request->user()->id,
        ]);

        $this->logger->record(
            $request->user(),
            $employee->id,
            before: null,
            after: $this->snapshot($leave),
            effectiveFrom: $leave->starts_at,
            reason: $request->validated('reason') ?? 'Leave recorded',
        );

        return EmployeeLeaveResource::make($leave->load('creator'))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, EmployeeLeave $employeeLeave): Response
    {
        $snapshot = $this->snapshot($employeeLeave);

        $employeeLeave->delete();

        $this->logger->record(
            $request->user(),
            $employeeLeave->employee_id,
            before: $snapshot,
            after: null,
            effectiveFrom: null,
            reason: $request->input('reason') ?? 'Leave cancelled',
        );

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(EmployeeLeave $leave): array
    {
        return [
            'type' => 'leave',
            'starts_at' => $leave->starts_at->toISOString(),
            'ends_at' => $leave->ends_at->toISOString(),
            'note' => $leave->note,
        ];
    }
}
