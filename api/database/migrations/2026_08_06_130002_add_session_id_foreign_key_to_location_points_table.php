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
        // Deferred from the migration that created location_points: that
        // migration predates tracking_sessions existing at all. Adding a
        // foreign key to a partitioned parent table is fully supported by
        // Postgres (11+) as long as the referenced table isn't itself
        // partitioned — tracking_sessions isn't — so the plain fluent
        // builder works here despite location_points' parent having been
        // created with raw DDL for PARTITION BY.
        Schema::table('location_points', function (Blueprint $table) {
            $table->foreign('session_id')->references('id')->on('tracking_sessions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('location_points', function (Blueprint $table) {
            $table->dropForeign(['session_id']);
        });
    }
};
