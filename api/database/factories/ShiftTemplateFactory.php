<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Standard',
            'timezone' => 'Asia/Muscat',
            // Oman's work week: Sunday(0)-Thursday(4). Matches the
            // days_of_week column's DB default.
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '07:00:00',
            'end_time' => '16:00:00',
            'grace_before_min' => 0,
            'grace_after_min' => 0,
            'max_daily_minutes' => null,
        ];
    }
}
