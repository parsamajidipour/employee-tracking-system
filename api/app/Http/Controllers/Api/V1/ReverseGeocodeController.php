<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseGeocodeRequest;
use App\Services\ReverseGeocoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class ReverseGeocodeController extends Controller
{
    public function __invoke(ReverseGeocodeRequest $request, ReverseGeocoder $geocoder): JsonResponse
    {
        try {
            $location = $geocoder->reverse(
                $request->float('lat'),
                $request->float('lng'),
            );
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Location lookup is temporarily unavailable.',
            ], 503);
        }

        return response()->json(['location' => $location]);
    }
}
