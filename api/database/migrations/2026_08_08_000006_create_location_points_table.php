<?php

use App\Services\LocationPointPartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE location_points (
                id bigint GENERATED ALWAYS AS IDENTITY,
                session_id bigint NULL REFERENCES tracking_sessions (id),
                employee_id bigint NOT NULL REFERENCES users (id),
                location geography(Point, 4326) NOT NULL,
                distance_m double precision NOT NULL DEFAULT 0,
                accuracy_m double precision NULL,
                speed_mps double precision NULL,
                heading_deg double precision NULL,
                battery_pct smallint NULL,
                is_mocked boolean NOT NULL DEFAULT false,
                recorded_at timestamp NOT NULL,
                received_at timestamp NOT NULL,
                created_at timestamp NULL,
                updated_at timestamp NULL,
                PRIMARY KEY (id, recorded_at)
            ) PARTITION BY RANGE (recorded_at)
        SQL);

        DB::statement('CREATE INDEX location_points_employee_id_recorded_at_index ON location_points (employee_id, recorded_at DESC)');
        DB::statement('CREATE INDEX location_points_location_index ON location_points USING GIST (location)');

        app(LocationPointPartitionManager::class)->ensure(CarbonImmutable::now());
    }

    public function down(): void
    {
        Schema::dropIfExists('location_points');
    }
};
