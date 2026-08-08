<?php

namespace Database\Factories;

use App\Models\EmployeeShift;
use App\Models\ShiftTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShift>
 */
class EmployeeShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'template_id' => ShiftTemplate::factory(),
        ];
    }
}
