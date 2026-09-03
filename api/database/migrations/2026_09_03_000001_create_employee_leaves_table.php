<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_leaves')) {
            Schema::create('employee_leaves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS employee_leaves_employee_id_starts_at_ends_at_index ON employee_leaves (employee_id, starts_at, ends_at) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
    }
};
