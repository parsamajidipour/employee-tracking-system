<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            // Both null while the session is open. App\Services\
            // TrackingSessionManager is the only writer of either.
            $table->timestamp('ended_at')->nullable();
            $table->enum('end_reason', ['window_closed', 'manual_pause', 'permission_revoked', 'stale'])->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Enforced in the database, not just in application code: at most
        // one open (ended_at IS NULL) session per employee at a time. This
        // is what actually prevents two open sessions if a race occurs
        // between two accepted batches for the same employee — see
        // App\Services\TrackingSessionManager::openOrReuse(), which expects
        // to lose that race sometimes and re-reads on conflict rather than
        // relying on application-level locking alone.
        DB::statement('CREATE UNIQUE INDEX tracking_sessions_one_open_per_employee ON tracking_sessions (employee_id) WHERE ended_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
