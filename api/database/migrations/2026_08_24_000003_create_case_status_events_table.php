<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('case_status_events')) {
            return;
        }

        Schema::create('case_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_case_id')->constrained('inspection_cases');
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['inspection_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_status_events');
    }
};
