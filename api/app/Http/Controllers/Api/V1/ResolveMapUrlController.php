<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveMapUrlRequest;
use App\Services\MapUrlResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;

class ResolveMapUrlController extends Controller
{
    public function __invoke(ResolveMapUrlRequest $request, MapUrlResolver $resolver): JsonResponse
    {
        try {
            $coordinates = $resolver->resolve($request->string('url')->toString());
        } catch (ConnectionException $exception) {
            report($exception);
            $coordinates = null;
        }

        if ($coordinates === null) {
            return response()->json(['message' => __('messages.map_url_invalid')], 422);
        }

        return response()->json($coordinates);
    }
}
