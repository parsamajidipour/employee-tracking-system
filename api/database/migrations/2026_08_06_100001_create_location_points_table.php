<?php

use App\Services\LocationPointPartitionManager;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Raw SQL, not Schema::create() — Laravel's schema builder has no
        // PARTITION BY support. Postgres requires the partition key
        // (recorded_at) to be part of every unique constraint on the table,
        // hence the composite primary key: `id` alone is still globally
        // unique (it's a plain identity sequence), the composite is a
        // Postgres partitioning requirement, not a modelling choice.
        DB::statement(<<<'SQL'
            CREATE TABLE location_points (
                id bigint GENERATED ALWAYS AS IDENTITY,
                session_id bigint NULL,
                employee_id bigint NOT NULL REFERENCES users (id),
                location geography(Point, 4326) NOT NULL,
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

        // Required index per SPEC section 4. Created on the partitioned
        // parent, which Postgres automatically propagates to every existing
        // and future partition — no need to repeat this per partition.
        DB::statement('CREATE INDEX location_points_employee_id_recorded_at_index ON location_points (employee_id, recorded_at)');

        // session_id has no FK: tracking_sessions doesn't exist yet and
        // isn't part of this change (TrackingGate doesn't manage session
        // lifecycle). The column is nullable and unpopulated for now; the FK
        // constraint lands as its own additive migration once
        // tracking_sessions exists.

        // Bootstrap partitions so the table is usable immediately, before
        // the scheduled `tracking:ensure-partitions` command has ever run.
        app(LocationPointPartitionManager::class)->ensure(CarbonImmutable::now());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropping the partitioned parent drops every partition with it —
        // partitions are not separately-owned tables, just storage for the
        // parent.
        Schema::dropIfExists('location_points');
    }
};
