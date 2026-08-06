<?php

namespace App\ValueObjects;

use App\Enums\ShiftWindowSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The active working-hours window for an employee at a given instant.
 *
 * effectiveStart()/effectiveEnd() are the primary public surface. Any
 * employee-facing or panel-facing display of "when is/was this window"
 * MUST use effectiveStart()/effectiveEnd(), never `start`/`end` directly —
 * grace is part of the window as far as anyone outside this class is
 * concerned. `start`/`end` are the *core*, un-graced boundaries as declared
 * by whichever template/exception produced this window; they exist so
 * internal logic (e.g. `contains()`) can apply grace explicitly, and so a
 * caller can distinguish "the shift" from "how much slack either edge
 * tolerates" if it genuinely needs to — not as a second display value.
 * Grace is exposed separately via `graceBeforeMin`/`graceAfterMin`, not
 * folded into start/end.
 *
 * Intervals in this domain are half-open: a window covers
 * [effectiveStart(), effectiveEnd()) — the start instant is inside the
 * window, the end instant is not.
 */
final readonly class ShiftWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ShiftWindowSource $source,
        public int $graceBeforeMin,
        public int $graceAfterMin,
    ) {
    }

    public function effectiveStart(): CarbonImmutable
    {
        return $this->start->subMinutes($this->graceBeforeMin);
    }

    public function effectiveEnd(): CarbonImmutable
    {
        return $this->end->addMinutes($this->graceAfterMin);
    }

    /**
     * Half-open containment: [effectiveStart(), effectiveEnd()).
     */
    public function contains(CarbonInterface $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->effectiveStart())
            && $instant->lessThan($this->effectiveEnd());
    }
}
