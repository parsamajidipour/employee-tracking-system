<?php

namespace App\ValueObjects;

final readonly class TrackingGateResult
{
    public function __construct(
        public int $accepted,
        public int $rejected,
    ) {
    }
}
