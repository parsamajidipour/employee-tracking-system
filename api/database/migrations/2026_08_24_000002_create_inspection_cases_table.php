<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inspection_cases')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE inspection_cases (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                reference_no varchar(255) NOT NULL,
                title varchar(255) NOT NULL,
                property_address varchar(255) NULL,
                location geography(Point, 4326) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                priority varchar(20) NOT NULL DEFAULT 'normal',
                assigned_to bigint NULL REFERENCES users (id),
                created_by bigint NULL REFERENCES users (id),
                assigned_at timestamp NULL,
                accepted_at timestamp NULL,
                planned_at timestamp NULL,
                started_at timestamp NULL,
                completed_at timestamp NULL,
                notes text NULL,
                created_at timestamp NULL,
                updated_at timestamp NULL
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX inspection_cases_reference_no_unique ON inspection_cases (reference_no)');
        DB::statement('CREATE INDEX inspection_cases_assigned_to_status_index ON inspection_cases (assigned_to, status)');
        DB::statement('CREATE INDEX inspection_cases_status_index ON inspection_cases (status)');
        DB::statement('CREATE INDEX inspection_cases_location_index ON inspection_cases USING GIST (location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_cases');
    }
};
