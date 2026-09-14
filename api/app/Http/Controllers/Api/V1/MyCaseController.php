<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptCaseRequest;
use App\Http\Requests\CaseNoteRequest;
use App\Http\Resources\CaseResource;
use App\Models\InspectionCase;
use App\Services\CaseLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use LogicException;

class MyCaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = InspectionCase::query()
            ->withLatLng()
            ->where(function (Builder $query) use ($request): void {
                $query->where('assigned_to', $request->user()->id)
                    ->orWhereHas('offers', fn (Builder $offers) => $offers->where('employee_id', $request->user()->id));
            })
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'overdue' THEN 1 WHEN 'accepted' THEN 2 WHEN 'in_progress' THEN 3 ELSE 4 END")
            ->orderByDesc('assigned_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return CaseResource::collection($query->get());
    }

    public function show(Request $request, InspectionCase $case): CaseResource
    {
        $this->authorizeAccess($case, $request->user()->id);

        return CaseResource::make(
            InspectionCase::query()->withLatLng()->with(['offers.employee', 'statusEvents.actor', 'photos'])->findOrFail($case->id),
        );
    }

    public function accept(AcceptCaseRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        try {
            $case = $lifecycle->accept($case, $request->user(), CarbonImmutable::parse($request->validated('planned_at')));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->with('offers.employee')->findOrFail($case->id));
    }

    public function reject(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        try {
            $case = $lifecycle->reject($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->with('offers.employee')->findOrFail($case->id));
    }

    public function start(Request $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        $this->authorizeAccess($case, $request->user()->id);

        try {
            $case = $lifecycle->start($case, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function complete(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        $this->authorizeAccess($case, $request->user()->id);

        try {
            $case = $lifecycle->complete($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function unseenCount(Request $request): JsonResponse
    {
        $visibleCaseIds = InspectionCase::query()
            ->where(function (Builder $query) use ($request): void {
                $query->where('assigned_to', $request->user()->id)
                    ->orWhereHas('offers', fn (Builder $offers) => $offers->where('employee_id', $request->user()->id));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $unreadNotifications = $request->user()->unreadNotifications()->get()->filter(function ($notification) use ($visibleCaseIds): bool {
            $type = $notification->data['type'] ?? null;
            if ($type === 'case.created') {
                return false;
            }

            $caseId = isset($notification->data['case_id']) ? (int) $notification->data['case_id'] : null;

            return $caseId === null || in_array($caseId, $visibleCaseIds, true);
        });

        return response()->json([
            'pending' => InspectionCase::query()->whereIn('id', $visibleCaseIds)->where('status', 'pending')->count(),
            'unread_notifications' => $unreadNotifications->count(),
        ]);
    }

    private function authorizeAccess(InspectionCase $case, int $employeeId): void
    {
        abort_unless(
            $case->assigned_to === $employeeId || $case->offers()->where('employee_id', $employeeId)->exists(),
            403,
        );
    }
}
