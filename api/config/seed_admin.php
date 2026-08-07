<?php

// Default admin account created by database/seeders/AdminUserSeeder.php —
// see README's "Trying the login flow". Routed through config (not env()
// directly in the seeder) per Laravel convention, since env() outside
// config files breaks under config caching. Irrelevant in production: the
// seeder refuses to run there regardless of what these resolve to.
return [
    'email' => env('SEED_ADMIN_EMAIL', 'test@example.com'),
    'password' => env('SEED_ADMIN_PASSWORD', 'password'),
];
