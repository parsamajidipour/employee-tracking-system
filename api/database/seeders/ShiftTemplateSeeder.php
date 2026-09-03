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
                'name' => 'Default',
                'days_of_week' => [0, 1, 2, 3, 4],
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'grace_before_min' => 15,
                'grace_after_min' => 15,
                'max_daily_minutes' => 540,
            ],
            [
                'name' => 'Ramadan',
                'days_of_week' => [0, 1, 2, 3, 4],
                'start_time' => '09:00:00',
                'end_time' => '15:00:00',
                'grace_before_min' => 15,
                'grace_after_min' => 15,
                'max_daily_minutes' => 360,
            ],
        ];

        foreach ($templates as $template) {
            ShiftTemplate::updateOrCreate(['name' => $template['name']], $template);
        }
    }
}
