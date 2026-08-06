<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
    }

    public function down(): void
    {
        // Deliberately not dropping the extension: other extensions
        // (postgis_topology, postgis_tiger_geocoder) can depend on it, and
        // CASCADE would remove objects this migration never created. Leaving
        // postgis installed on rollback is harmless; re-running up() is a
        // no-op either way (CREATE EXTENSION IF NOT EXISTS).
    }
};
