<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Guarantees a working admin login after every migrate:fresh --seed. Past
 * attempts at this went by hand in tinker after every database reset,
 * which cost real debugging time more than once — see DECISIONS.md.
 *
 * updateOrCreate() makes this idempotent and safe to re-run against an
 * existing database: it always re-asserts the documented default
 * password/role/active state, rather than silently no-op'ing if the row
 * already exists in some other (e.g. deactivated, password-changed) state.
 *
 * Never runs in production — these are documented, publicly-known-default
 * dev/staging credentials (see config/seed_admin.php), which is exactly
 * wrong for an environment holding real employee location data.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn(
                'AdminUserSeeder: skipped — never seeds a documented-default admin account in production.',
            );

            return;
        }

        User::updateOrCreate(
            ['email' => config('seed_admin.email')],
            [
                'name' => 'Admin',
                'password' => Hash::make(config('seed_admin.password')),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
