<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_photos')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE case_photos (
                id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                inspection_case_id bigint NOT NULL REFERENCES inspection_cases (id),
                employee_id bigint NOT NULL REFERENCES users (id),
                disk_path varchar(255) NOT NULL,
                location geography(Point, 4326) NOT NULL,
                accuracy_m double precision NULL,
                distance_from_case_m double precision NOT NULL,
                is_gps_verified boolean NOT NULL DEFAULT false,
                captured_at timestamp NOT NULL,
                created_at timestamp NULL,
                updated_at timestamp NULL
            )
        SQL);

        DB::statement('CREATE INDEX case_photos_inspection_case_id_index ON case_photos (inspection_case_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('case_photos');
    }
};
