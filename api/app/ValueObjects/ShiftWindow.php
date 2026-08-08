<?php

namespace App\ValueObjects;

use App\Enums\ShiftWindowSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class ShiftWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public ShiftWindowSource $source,
        public int $graceBeforeMin,
        public int $graceAfterMin,
    ) {}

    public function effectiveStart(): CarbonImmutable
    {
        return $this->start->subMinutes($this->graceBeforeMin);
    }

    public function effectiveEnd(): CarbonImmutable
    {
        return $this->end->addMinutes($this->graceAfterMin);
    }

    public function contains(CarbonInterface $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->effectiveStart())
            && $instant->lessThan($this->effectiveEnd());
    }

    /**
     * @return array{start: string, end: string, source: string}
     */
    public function toApiArray(): array
    {
        return [
            'start' => $this->effectiveStart()->toISOString(),
            'end' => $this->effectiveEnd()->toISOString(),
            'source' => $this->source->value,
        ];
    }
}
