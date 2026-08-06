<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schedule_change_log', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete on both, not cascade/nullOnDelete: deleting a
            // user must never take their audit history with them — the
            // append-only guarantee on this table (CLAUDE.md) would
            // otherwise be silently defeated by a FK cascade.
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_employee_id')->constrained('users')->restrictOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            // Only meaningful for employee_shifts changes (their own
            // effective_from). shift_exceptions changes log this as null —
            // see App\Services\ScheduleChangeLogger.
            $table->timestamp('effective_from')->nullable();
            $table->text('reason')->nullable();
            // created_at only, per SPEC section 4 — no updated_at column
            // exists, matching the append-only design (nothing ever updates
            // a row here to need one).
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_id');
            $table->index('target_employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_change_log');
    }
};
