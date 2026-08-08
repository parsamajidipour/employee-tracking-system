<?php

namespace Database\Factories;

use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard',
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
            'grace_before_min' => 0,
            'grace_after_min' => 0,
            'max_daily_minutes' => null,
        ];
    }

    public function nightShift(): static
    {
        return $this->state([
            'name' => 'Night',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);
    }

    public function withGrace(int $before, int $after): static
    {
        return $this->state([
            'grace_before_min' => $before,
            'grace_after_min' => $after,
        ]);
    }
}
