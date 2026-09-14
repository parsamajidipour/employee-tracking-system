<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\InspectionCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $visibleCaseIds = $this->visibleCaseIds($request);
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(100)
            ->get()
            ->filter(function ($notification) use ($visibleCaseIds): bool {
                if (($notification->data['type'] ?? null) === 'case.created') {
                    return false;
                }

                $caseId = isset($notification->data['case_id']) ? (int) $notification->data['case_id'] : null;

                return $caseId === null || in_array($caseId, $visibleCaseIds, true);
            })
            ->take(50)
            ->values();

        return response()->json([
            'data' => NotificationResource::collection($notifications)->resolve(),
            'unread_count' => $notifications->whereNull('read_at')->count(),
            'visible_case_ids' => $visibleCaseIds,
        ]);
    }

    public function markRead(Request $request, string $notification): Response
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function markAllRead(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * @return list<int>
     */
    private function visibleCaseIds(Request $request): array
    {
        return InspectionCase::query()
            ->when($request->user()->role === UserRole::Employee, function (Builder $query) use ($request): void {
                $query->where(function (Builder $cases) use ($request): void {
                    $cases->where('assigned_to', $request->user()->id)
                        ->orWhereHas('offers', fn (Builder $offers) => $offers->where('employee_id', $request->user()->id));
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
