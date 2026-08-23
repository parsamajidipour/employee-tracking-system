<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrackingInterruptionRequest;
use App\Services\TrackingInterruptionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingInterruptionController extends Controller
{
    public function start(StoreTrackingInterruptionRequest $request, TrackingInterruptionService $interruptions): JsonResponse
    {
        $interruption = $interruptions->start(
            $request->user(),
            $request->validated('reason'),
            CarbonImmutable::parse($request->validated('at')),
        );

        return response()->json(['accepted' => $interruption !== null]);
    }

    public function stop(Request $request, TrackingInterruptionService $interruptions): JsonResponse
    {
        $validated = $request->validate(['at' => ['required', 'date']]);

        $interruptions->stop($request->user(), CarbonImmutable::parse($validated['at']));

        return response()->json(['accepted' => true]);
    }
}
