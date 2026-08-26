<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_assignment_histories')) {
            return;
        }

        Schema::create('case_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_case_id')->constrained('inspection_cases')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['inspection_case_id', 'employee_id', 'assigned_at']);
            $table->index(['employee_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignment_histories');
    }
};
