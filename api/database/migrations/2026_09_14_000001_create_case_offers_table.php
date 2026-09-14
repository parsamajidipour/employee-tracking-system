<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('case_offers')) {
            Schema::create('case_offers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inspection_case_id')->constrained('inspection_cases')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('users');
                $table->foreignId('offered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('offered_at')->useCurrent();

                $table->unique(['inspection_case_id', 'employee_id']);
                $table->index(['employee_id', 'offered_at']);
            });
        }

        DB::table('notifications')
            ->whereRaw("data::jsonb ->> 'type' = ?", ['case.created'])
            ->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('case_offers');
    }
};
