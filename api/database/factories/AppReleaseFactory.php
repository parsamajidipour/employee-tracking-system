<?php

namespace Database\Factories;

use App\Models\AppRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppRelease>
 */
class AppReleaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_code' => $this->faker->unique()->numberBetween(1, 1000),
            'version_name' => '1.0.0',
            'file_path' => 'releases/app-v1.apk',
            'file_size' => 10_000_000,
            'release_notes' => null,
            'is_mandatory' => false,
        ];
    }
}
