<?php

namespace Database\Factories;

use App\Enums\ShiftExceptionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftException>
 */
class ShiftExceptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'date' => now()->toDateString(),
            'type' => ShiftExceptionType::Leave,
            'start_at' => null,
            'end_at' => null,
            'note' => null,
        ];
    }

    public function leave(): static
    {
        return $this->state([
            'type' => ShiftExceptionType::Leave,
            'start_at' => null,
            'end_at' => null,
        ]);
    }

    public function holiday(): static
    {
        return $this->state([
            'type' => ShiftExceptionType::Holiday,
            'start_at' => null,
            'end_at' => null,
        ]);
    }

    public function overtime(string $startAt, string $endAt): static
    {
        return $this->state([
            'type' => ShiftExceptionType::Overtime,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }

    public function earlyEnd(string $startAt, string $endAt): static
    {
        return $this->state([
            'type' => ShiftExceptionType::EarlyEnd,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);
    }
}
