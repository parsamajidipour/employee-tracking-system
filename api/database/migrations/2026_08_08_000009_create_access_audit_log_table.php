<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_employee_id')->constrained('users')->restrictOnDelete();
            $table->enum('action', ['view_trail', 'export_trail', 'view_live_position']);
            $table->ipAddress('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('CREATE INDEX access_audit_log_target_employee_id_created_at_index ON access_audit_log (target_employee_id, created_at DESC)');
        DB::statement('CREATE INDEX access_audit_log_actor_id_created_at_index ON access_audit_log (actor_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_log');
    }
};
