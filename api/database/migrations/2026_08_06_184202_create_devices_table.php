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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_identifier');
            $table->string('device_name')->nullable();
            // Which token this device's login minted, so App\Services\
            // DeviceService::revoke() knows exactly which
            // personal_access_tokens row to delete. Not the FK Sanctum
            // itself uses (that's tokenable_id/tokenable_type on
            // personal_access_tokens, pointing back the other way) — this
            // is purely so a revoke can find its token.
            $table->foreignId('personal_access_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });

        // Enforced in the database, not just in application code: at most
        // one active (revoked_at IS NULL) device per employee at a time —
        // same pattern as tracking_sessions' "at most one open session"
        // index. App\Services\DeviceService::login() expects to lose a
        // race here sometimes and re-reads on conflict rather than trying
        // to prevent the race itself.
        DB::statement('CREATE UNIQUE INDEX devices_one_active_per_employee ON devices (employee_id) WHERE revoked_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
