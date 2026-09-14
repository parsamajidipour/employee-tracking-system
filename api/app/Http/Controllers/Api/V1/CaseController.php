<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CaseAccessChanged;
use App\Events\CaseChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCaseRequest;
use App\Http\Requests\CaseNoteRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Resources\CaseResource;
use App\Models\InspectionCase;
use App\Models\User;
use App\Services\CaseAssignmentService;
use App\Services\CaseLifecycleService;
use App\Services\CaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

class CaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InspectionCase::query()->withLatLng()->with(['assignee', 'offers.employee'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('assigned_to')) {
            $employeeId = $request->integer('assigned_to');
            $query->where(function ($cases) use ($employeeId): void {
                $cases->where('assigned_to', $employeeId)
                    ->orWhereHas('offers', fn ($offers) => $offers->where('employee_id', $employeeId));
            });
        }

        if ($request->filled('created_date')) {
            $request->validate(['created_date' => ['date_format:Y-m-d']]);
            $query->whereDate('created_at', $request->string('created_date')->toString());
        }

        return CaseResource::collection($query->paginate(50));
    }

    public function show(InspectionCase $case): CaseResource
    {
        return CaseResource::make(
            InspectionCase::query()
                ->withLatLng()
                ->with(['assignee', 'creator', 'offers.employee', 'statusEvents.actor', 'photos'])
                ->findOrFail($case->id),
        );
    }

    public function store(StoreCaseRequest $request, CaseLifecycleService $lifecycle): JsonResponse
    {
        $case = $lifecycle->create($request->validated(), $request->user());

        return CaseResource::make(
            InspectionCase::query()->withLatLng()->with(['assignee', 'offers.employee'])->findOrFail($case->id),
        )->response()->setStatusCode(201);
    }

    public function nearestSurveyors(InspectionCase $case, CaseAssignmentService $assignment): JsonResponse
    {
        $case = InspectionCase::query()->withLatLng()->findOrFail($case->id);

        return response()->json($assignment->rank($case)->map->toArray()->values());
    }

    public function assign(AssignCaseRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        $employees = User::query()
            ->employees()
            ->active()
            ->whereIn('id', $request->validated('employee_ids'))
            ->get();

        try {
            $case = $lifecycle->offer($case, $employees, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->with(['assignee', 'offers.employee'])->findOrFail($case->id));
    }

    public function cancel(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        try {
            $case = $lifecycle->cancel($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->with(['assignee', 'offers.employee'])->findOrFail($case->id));
    }

    public function destroy(InspectionCase $case, CaseNotificationService $notifications): Response
    {
        $caseId = $case->id;
        $photoPaths = $case->photos()->pluck('disk_path')->all();
        $employeeIds = $case->offers()->pluck('employee_id')->push($case->assigned_to)->filter()->unique()->values()->all();

        DB::transaction(function () use ($case): void {
            $case->statusEvents()->delete();
            $case->photos()->delete();
            $case->offers()->delete();
            $case->delete();
        });

        $notifications->delete($caseId);

        if ($photoPaths !== []) {
            Storage::disk('local')->delete($photoPaths);
        }

        event(CaseChanged::deleted($caseId));
        if ($employeeIds !== []) {
            event(new CaseAccessChanged($caseId, $employeeIds));
        }

        return response()->noContent();
    }
}
