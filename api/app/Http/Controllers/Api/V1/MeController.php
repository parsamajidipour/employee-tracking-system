<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ShiftWindowResolver;
use App\ValueObjects\ShiftWindow;
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
            'current' => $this->format($current),
            'next' => $this->format($next),
            'server_time' => $now->toISOString(),
        ]);
    }

    /**
     * Employee-facing output always uses the graced times
     * (effectiveStart()/effectiveEnd()), never the core start/end — see
     * App\ValueObjects\ShiftWindow's docblock.
     */
    private function format(?ShiftWindow $window): ?array
    {
        if ($window === null) {
            return null;
        }

        return [
            'start' => $window->effectiveStart()->toISOString(),
            'end' => $window->effectiveEnd()->toISOString(),
            'source' => $window->source->value,
        ];
    }
}
