<?php

namespace Tests\Unit\ValueObjects;

use App\Enums\ShiftWindowSource;
use App\ValueObjects\ShiftWindow;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ShiftWindowTest extends TestCase
{
    private function window(int $graceBeforeMin = 0, int $graceAfterMin = 0): ShiftWindow
    {
        return new ShiftWindow(
            CarbonImmutable::parse('2026-08-06 07:00:00', 'UTC'),
            CarbonImmutable::parse('2026-08-06 16:00:00', 'UTC'),
            ShiftWindowSource::DefaultTemplate,
            $graceBeforeMin,
            $graceAfterMin,
        );
    }

    public function test_effective_start_and_end_apply_grace(): void
    {
        $window = $this->window(graceBeforeMin: 10, graceAfterMin: 15);

        $this->assertTrue($window->effectiveStart()->equalTo(CarbonImmutable::parse('2026-08-06 06:50:00', 'UTC')));
        $this->assertTrue($window->effectiveEnd()->equalTo(CarbonImmutable::parse('2026-08-06 16:15:00', 'UTC')));
    }

    public function test_contains_is_inclusive_at_the_start(): void
    {
        $window = $this->window();

        $this->assertTrue($window->contains(CarbonImmutable::parse('2026-08-06 07:00:00', 'UTC')));
    }

    public function test_contains_is_exclusive_at_the_end(): void
    {
        $window = $this->window();

        $this->assertFalse($window->contains(CarbonImmutable::parse('2026-08-06 16:00:00', 'UTC')));
        $this->assertTrue($window->contains(CarbonImmutable::parse('2026-08-06 15:59:59', 'UTC')));
    }

    public function test_contains_respects_grace_at_both_edges(): void
    {
        $window = $this->window(graceBeforeMin: 10, graceAfterMin: 15);

        $this->assertFalse($window->contains(CarbonImmutable::parse('2026-08-06 06:49:59', 'UTC')));
        $this->assertTrue($window->contains(CarbonImmutable::parse('2026-08-06 06:50:00', 'UTC')));
        $this->assertTrue($window->contains(CarbonImmutable::parse('2026-08-06 16:14:59', 'UTC')));
        $this->assertFalse($window->contains(CarbonImmutable::parse('2026-08-06 16:15:00', 'UTC')));
    }

    public function test_start_and_end_are_immutable_core_values_unaffected_by_grace(): void
    {
        $window = $this->window(graceBeforeMin: 10, graceAfterMin: 15);

        $this->assertTrue($window->start->equalTo(CarbonImmutable::parse('2026-08-06 07:00:00', 'UTC')));
        $this->assertTrue($window->end->equalTo(CarbonImmutable::parse('2026-08-06 16:00:00', 'UTC')));
    }
}
