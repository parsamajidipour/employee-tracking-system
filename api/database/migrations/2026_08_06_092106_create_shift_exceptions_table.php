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
        Schema::create('shift_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('type', ['leave', 'holiday', 'overtime', 'early_end']);
            // Clock time on `date`, in the employee's team timezone. Null
            // for leave/holiday (no window that day); populated for
            // overtime/early_end (defines the window for that date).
            $table->time('start_at')->nullable();
            $table->time('end_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // One exception per employee per date — two rows for the same
            // day would be ambiguous to resolve.
            $table->unique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_exceptions');
    }
};
