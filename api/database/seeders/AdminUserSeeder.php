<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('role', UserRole::Admin)->exists()) {
            return;
        }

        User::create([
            'name' => config('seed_admin.name'),
            'email' => config('seed_admin.email'),
            'username' => 'admin',
            'phone' => '90000000',
            'password' => Hash::make(config('seed_admin.password')),
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
