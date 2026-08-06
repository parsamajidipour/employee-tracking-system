<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ShiftWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __construct(private readonly ShiftWindowResolver $resolver)
    {
    }

    public function window(Request $request): JsonResponse
    {
        $employee = $request->user();
        $now = CarbonImmutable::now();

        $current = $this->resolver->resolve($employee, $now);
        $next = $this->resolver->resolveNext($employee, $now);

        return response()->json([
            'current' => $current?->toApiArray(),
            'next' => $next?->toApiArray(),
            'server_time' => $now->toISOString(),
        ]);
    }
}
