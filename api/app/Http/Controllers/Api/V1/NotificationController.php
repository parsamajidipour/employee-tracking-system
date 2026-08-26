<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn ($notification) => ($notification->data['type'] ?? null) !== 'case.created')
            ->take(50)
            ->values();

        return response()->json([
            'data' => NotificationResource::collection($notifications)->resolve(),
            'unread_count' => $request->user()
                ->unreadNotifications()
                ->get()
                ->filter(fn ($notification) => ($notification->data['type'] ?? null) !== 'case.created')
                ->count(),
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
}
