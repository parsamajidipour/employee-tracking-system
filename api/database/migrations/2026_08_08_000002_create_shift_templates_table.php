<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('days_of_week')->default('[0,1,2,3,4]');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('grace_before_min')->default(0);
            $table->unsignedSmallInteger('grace_after_min')->default(0);
            $table->unsignedSmallInteger('max_daily_minutes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
