<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_status_events')) {
            DB::statement('ALTER TABLE case_status_events DROP CONSTRAINT IF EXISTS case_status_events_inspection_case_id_foreign');
            DB::statement('ALTER TABLE case_status_events ADD CONSTRAINT case_status_events_inspection_case_id_foreign FOREIGN KEY (inspection_case_id) REFERENCES inspection_cases (id) ON DELETE CASCADE');
        }

        if (Schema::hasTable('case_photos')) {
            DB::statement('ALTER TABLE case_photos DROP CONSTRAINT IF EXISTS case_photos_inspection_case_id_fkey');
            DB::statement('ALTER TABLE case_photos ADD CONSTRAINT case_photos_inspection_case_id_fkey FOREIGN KEY (inspection_case_id) REFERENCES inspection_cases (id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('case_status_events')) {
            DB::statement('ALTER TABLE case_status_events DROP CONSTRAINT IF EXISTS case_status_events_inspection_case_id_foreign');
            DB::statement('ALTER TABLE case_status_events ADD CONSTRAINT case_status_events_inspection_case_id_foreign FOREIGN KEY (inspection_case_id) REFERENCES inspection_cases (id)');
        }

        if (Schema::hasTable('case_photos')) {
            DB::statement('ALTER TABLE case_photos DROP CONSTRAINT IF EXISTS case_photos_inspection_case_id_fkey');
            DB::statement('ALTER TABLE case_photos ADD CONSTRAINT case_photos_inspection_case_id_fkey FOREIGN KEY (inspection_case_id) REFERENCES inspection_cases (id)');
        }
    }
};
