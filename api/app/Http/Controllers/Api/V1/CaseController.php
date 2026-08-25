<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignCaseRequest;
use App\Http\Requests\CaseNoteRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Resources\CaseResource;
use App\Models\InspectionCase;
use App\Models\User;
use App\Notifications\CaseCreatedNotification;
use App\Services\CaseAssignmentService;
use App\Services\CaseLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use LogicException;

class CaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InspectionCase::query()->withLatLng()->with(['assignee'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        return CaseResource::collection($query->paginate(50));
    }

    public function show(InspectionCase $case): CaseResource
    {
        return CaseResource::make(
            InspectionCase::query()
                ->withLatLng()
                ->with(['assignee', 'creator', 'statusEvents.actor', 'photos'])
                ->findOrFail($case->id),
        );
    }

    public function store(StoreCaseRequest $request, CaseLifecycleService $lifecycle): JsonResponse
    {
        $case = $lifecycle->create($request->validated(), $request->user());

        if ($request->filled('assigned_to')) {
            $lifecycle->assign($case, User::findOrFail($request->integer('assigned_to')), $request->user());
        }

        $employees = User::query()->employees()->active()->get();

        if ($employees->isNotEmpty()) {
            Notification::send($employees, new CaseCreatedNotification($case));
        }

        return CaseResource::make(
            InspectionCase::query()->withLatLng()->with('assignee')->findOrFail($case->id),
        )->response()->setStatusCode(201);
    }

    public function nearestSurveyors(InspectionCase $case, CaseAssignmentService $assignment): JsonResponse
    {
        return response()->json($assignment->rank($case)->map->toArray()->values());
    }

    public function assign(AssignCaseRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        if ($case->status !== CaseStatus::Pending) {
            return response()->json(['message' => 'Only a pending, unaccepted case can be assigned or reassigned.'], 409);
        }

        $employee = User::query()->employees()->findOrFail($request->validated('employee_id'));
        $case = $lifecycle->assign($case, $employee, $request->user());

        return CaseResource::make(InspectionCase::query()->withLatLng()->with('assignee')->findOrFail($case->id));
    }

    public function cancel(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        try {
            $case = $lifecycle->cancel($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->with('assignee')->findOrFail($case->id));
    }

    public function destroy(InspectionCase $case): Response
    {
        abort_if($case->status !== CaseStatus::Pending, 409, 'Only unaccepted, unassigned cases can be deleted.');

        $case->delete();

        return response()->noContent();
    }
}
