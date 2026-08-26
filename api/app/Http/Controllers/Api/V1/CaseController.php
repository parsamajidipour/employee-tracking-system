<?php

namespace App\Http\Controllers\Api\V1;

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
        $query = InspectionCase::query()->withLatLng()->with(['assignee'])->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
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
                ->with(['assignee', 'creator', 'statusEvents.actor', 'photos'])
                ->findOrFail($case->id),
        );
    }

    public function store(StoreCaseRequest $request, CaseLifecycleService $lifecycle): JsonResponse
    {
        $case = $lifecycle->create($request->validated(), $request->user());

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
        $employee = User::query()->employees()->findOrFail($request->validated('employee_id'));

        try {
            $case = $lifecycle->assign($case, $employee, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

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
        $caseId = $case->id;
        $photoPaths = $case->photos()->pluck('disk_path')->all();

        DB::transaction(function () use ($case): void {
            // Keep this explicit as well as using cascading foreign keys so
            // deletion remains safe during rolling deployments where the new
            // constraint migration may not have run yet.
            $case->statusEvents()->delete();
            $case->photos()->delete();
            $case->delete();
        });

        if ($photoPaths !== []) {
            Storage::disk('local')->delete($photoPaths);
        }

        event(CaseChanged::deleted($caseId));

        return response()->noContent();
    }
}
