<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptCaseRequest;
use App\Http\Requests\CaseNoteRequest;
use App\Http\Resources\CaseResource;
use App\Models\InspectionCase;
use App\Services\CaseLifecycleService;
use Carbon\CarbonImmutable;
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
            ->where('assigned_to', $request->user()->id)
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END")
            ->orderByDesc('assigned_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return CaseResource::collection($query->get());
    }

    public function show(Request $request, InspectionCase $case): CaseResource
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        return CaseResource::make(
            InspectionCase::query()->withLatLng()->with(['statusEvents.actor', 'photos'])->findOrFail($case->id),
        );
    }

    public function accept(AcceptCaseRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        try {
            $case = $lifecycle->accept($case, $request->user(), CarbonImmutable::parse($request->validated('planned_at')));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function reject(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        try {
            $case = $lifecycle->reject($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function start(Request $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        try {
            $case = $lifecycle->start($case, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function complete(CaseNoteRequest $request, InspectionCase $case, CaseLifecycleService $lifecycle): CaseResource|JsonResponse
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        try {
            $case = $lifecycle->complete($case, $request->user(), $request->validated('note'));
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return CaseResource::make(InspectionCase::query()->withLatLng()->findOrFail($case->id));
    }

    public function unseenCount(Request $request): JsonResponse
    {
        return response()->json([
            'pending' => InspectionCase::where('assigned_to', $request->user()->id)->where('status', 'pending')->count(),
            'unread_notifications' => $request->user()->unreadNotifications()->count(),
        ]);
    }
}
