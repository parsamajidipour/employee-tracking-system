<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackRequest;
use App\Services\TrackingGate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TrackController extends Controller
{
    public function __construct(private readonly TrackingGate $gate)
    {
    }

    public function store(StoreTrackRequest $request): JsonResponse
    {
        $result = $this->gate->process($request->user(), $request->validated('points'));

        // Rejection is not an error — a 202 is returned either way, per
        // SPEC section 5. The mobile client deletes the whole batch from its
        // local queue on any 202, accepted or rejected.
        return response()->json([
            'accepted' => $result->accepted,
            'rejected' => $result->rejected,
            'server_time' => CarbonImmutable::now()->toISOString(),
        ], 202);
    }
}
