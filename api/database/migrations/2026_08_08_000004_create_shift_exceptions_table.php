<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['leave', 'holiday', 'overtime', 'early_end']);
            $table->time('start_at')->nullable();
            $table->time('end_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX shift_exceptions_employee_id_date_unique ON shift_exceptions (employee_id, date) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_exceptions');
    }
};
