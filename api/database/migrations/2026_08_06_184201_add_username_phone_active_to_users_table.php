<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
            // Nullable + unique: Postgres allows any number of NULLs in a
            // unique index (NULL <> NULL), which is exactly what's needed —
            // required for employees (device login uses this instead of
            // email), left null for every other role.
            $table->string('username')->nullable()->unique()->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Employees no longer need an email at all now that device login
        // runs on username/password. Raw SQL, not Schema::table(...)->
        // change() — changing a column's nullability needs doctrine/dbal,
        // which isn't a dependency of this project.
        DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN email SET NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'username', 'is_active']);
        });
    }
};
