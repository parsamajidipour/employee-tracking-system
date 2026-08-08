<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_change_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_employee_id')->constrained('users')->restrictOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('CREATE INDEX schedule_change_log_target_employee_id_created_at_index ON schedule_change_log (target_employee_id, created_at DESC)');
        DB::statement('CREATE INDEX schedule_change_log_actor_id_created_at_index ON schedule_change_log (actor_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_change_log');
    }
};
