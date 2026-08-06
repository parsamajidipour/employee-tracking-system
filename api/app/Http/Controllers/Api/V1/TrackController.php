<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class TrackController extends Controller
{
    public function __construct(private readonly TrackingGate $gate)
    {
    }

    public function store(StoreTrackRequest $request): JsonResponse
    {
        $result = $this->gate->process($request->user(), $request->validated('points'));

        $this->touchDevice($request->user());

        // Rejection is not an error — a 202 is returned either way, per
        // SPEC section 5. The mobile client deletes the whole batch from its
        // local queue on any 202, accepted or rejected.
        return response()->json([
            'accepted' => $result->accepted,
            'rejected' => $result->rejected,
            'server_time' => CarbonImmutable::now()->toISOString(),
        ], 202);
    }

    /**
     * A hit on /track proves the device is alive right now, independent of
     * whether any point in the batch was actually accepted — a batch that's
     * entirely rejected (e.g. every point outside the window) still means
     * the phone itself is online and authenticated. Only a real
     * device-bound token (App\Services\DeviceService::login()) has a
     * device row to update; the mobile app never authenticates any other
     * way, but this stays defensive rather than assuming.
     */
    private function touchDevice(User $employee): void
    {
        $token = $employee->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        Device::where('personal_access_token_id', $token->id)->update(['last_seen_at' => now()]);
    }
}
