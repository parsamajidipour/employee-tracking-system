<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrackingSession>
 */
class TrackingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'started_at' => now(),
            'ended_at' => null,
            'end_reason' => null,
        ];
    }
}
