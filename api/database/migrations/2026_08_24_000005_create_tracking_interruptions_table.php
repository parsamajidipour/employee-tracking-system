<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tracking_interruptions')) {
            return;
        }

        Schema::create('tracking_interruptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users');
            $table->string('reason', 30);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_interruptions');
    }
};
