<?php

namespace Database\Seeders;

use App\Models\AppRelease;
use Illuminate\Database\Seeder;

class AppReleaseSeeder extends Seeder
{
    public function run(): void
    {
        AppRelease::updateOrCreate(
            ['version_code' => 1],
            [
                'version_name' => '1.0.0',
                'file_path' => 'releases/app-v1.apk',
                'file_size' => 0,
                'release_notes' => 'Initial release.',
                'is_mandatory' => false,
            ],
        );
    }
}
