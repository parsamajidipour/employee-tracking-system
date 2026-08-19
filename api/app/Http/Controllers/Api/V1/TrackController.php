<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePingRequest;
use App\Http\Requests\StoreTrackRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class TrackController extends Controller
{
    public function __construct(private readonly TrackingGate $gate) {}

    public function store(StoreTrackRequest $request): JsonResponse
    {
        $result = $this->gate->process($request->user(), $request->validated('points'));

        $this->touchDevice($request->user());

        return response()->json([
            'accepted' => $result->accepted,
            'rejected' => $result->rejected,
            'server_time' => CarbonImmutable::now()->toISOString(),
        ], 202);
    }

    public function ping(StorePingRequest $request): JsonResponse
    {
        $accepted = $this->gate->ping($request->user(), $request->validated());

        $this->touchDevice($request->user());

        return response()->json(['accepted' => $accepted]);
    }

    private function touchDevice(User $employee): void
    {
        $token = $employee->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        Device::where('personal_access_token_id', $token->id)->update(['last_seen_at' => now()]);
    }
}
