<?php

namespace Database\Seeders;

use App\Models\ShiftTemplate;
use Illuminate\Database\Seeder;

class ShiftTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Day Shift',
                'days_of_week' => [0, 1, 2, 3, 4],
                'start_time' => '07:00:00',
                'end_time' => '16:00:00',
                'grace_before_min' => 15,
                'grace_after_min' => 15,
                'max_daily_minutes' => 540,
            ],
            [
                'name' => 'Night Shift',
                'days_of_week' => [6, 0, 1, 2, 3],
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'grace_before_min' => 10,
                'grace_after_min' => 10,
                'max_daily_minutes' => 480,
            ],
            [
                'name' => 'Weekend Half Day',
                'days_of_week' => [5],
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'grace_before_min' => 0,
                'grace_after_min' => 0,
                'max_daily_minutes' => 240,
            ],
        ];

        foreach ($templates as $template) {
            ShiftTemplate::updateOrCreate(['name' => $template['name']], $template);
        }
    }
}
