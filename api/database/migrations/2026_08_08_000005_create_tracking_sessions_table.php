<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->enum('end_reason', ['window_closed', 'manual_pause', 'permission_revoked', 'stale'])->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX tracking_sessions_employee_id_started_at_index ON tracking_sessions (employee_id, started_at DESC)');
        DB::statement('CREATE UNIQUE INDEX tracking_sessions_one_open_per_employee ON tracking_sessions (employee_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
