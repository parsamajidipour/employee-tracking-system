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
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            // Informational only — never read by ShiftWindowResolver. The
            // resolver always resolves in the employee's team timezone
            // (CLAUDE.md is explicit there is exactly one timezone source).
            // Kept because SPEC section 4 lists it; must match the team's
            // timezone in practice, validated at write time elsewhere, not
            // here.
            $table->string('timezone');
            // 0=Sunday..6=Saturday, matching Carbon::dayOfWeek and Postgres
            // EXTRACT(DOW ...). Default is Oman's work week, Sunday-Thursday.
            $table->json('days_of_week')->default('[0,1,2,3,4]');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_before_min')->default(0);
            $table->unsignedSmallInteger('grace_after_min')->default(0);
            // Migrated per SPEC section 4; not interpreted by the resolver.
            // Daily-minute capping is a downstream concern (TrackingGate or
            // an aggregate job), not "what window is active right now."
            $table->unsignedSmallInteger('max_daily_minutes')->nullable();
            $table->timestamps();

            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
