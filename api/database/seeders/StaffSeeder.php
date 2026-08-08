<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('StaffSeeder: skipped in production.');

            return;
        }

        $staff = [
            ['name' => 'Hana Al Balushi', 'username' => 'hr', 'email' => 'hr@example.com', 'role' => UserRole::Hr, 'phone' => '91000001'],
            ['name' => 'Salim Al Hinai', 'username' => 'supervisor', 'email' => 'supervisor@example.com', 'role' => UserRole::Supervisor, 'phone' => '91000002'],
            ['name' => 'Maryam Al Riyami', 'username' => 'supervisor2', 'email' => 'supervisor2@example.com', 'role' => UserRole::Supervisor, 'phone' => '91000003'],
        ];

        foreach ($staff as $person) {
            User::updateOrCreate(['username' => $person['username']], [
                ...$person,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $employees = [
            ['name' => 'Ahmed Al Saadi', 'username' => 'ahmed', 'phone' => '92000001', 'is_active' => true],
            ['name' => 'Fatma Al Kindi', 'username' => 'fatma', 'phone' => '92000002', 'is_active' => true],
            ['name' => 'Yusuf Al Harthy', 'username' => 'yusuf', 'phone' => '92000003', 'is_active' => true],
            ['name' => 'Noura Al Amri', 'username' => 'noura', 'phone' => '92000004', 'is_active' => true],
            ['name' => 'Khalid Al Rawahi', 'username' => 'khalid', 'phone' => '92000005', 'is_active' => true],
            ['name' => 'Aisha Al Zadjali', 'username' => 'aisha', 'phone' => '92000006', 'is_active' => true],
            ['name' => 'Omar Al Maskari', 'username' => 'omar', 'phone' => '92000007', 'is_active' => true],
            ['name' => 'Layla Al Habsi', 'username' => 'layla', 'phone' => '92000008', 'is_active' => false],
        ];

        foreach ($employees as $employee) {
            User::updateOrCreate(['username' => $employee['username']], [
                ...$employee,
                'email' => null,
                'password' => Hash::make(self::DEFAULT_PASSWORD),
                'role' => UserRole::Employee,
            ]);
        }
    }
}
