<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ShiftTemplateSeeder::class,
            StaffSeeder::class,
            ScheduleSeeder::class,
            TrackingDemoSeeder::class,
            CaseSeeder::class,
            AppReleaseSeeder::class,
        ]);
    }
}
