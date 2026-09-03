<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['employee_shifts', 'shift_exceptions', 'shift_templates'] as $table) {
            if (! $this->columnExists($table, 'deleted_at')) {
                DB::statement("ALTER TABLE {$table} ADD COLUMN deleted_at timestamp NULL");
            }
        }

        DB::statement('ALTER TABLE shift_exceptions DROP CONSTRAINT IF EXISTS shift_exceptions_employee_id_date_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS shift_exceptions_employee_id_date_unique ON shift_exceptions (employee_id, date) WHERE deleted_at IS NULL');

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique ON users (email) WHERE email IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_username_unique ON users (username) WHERE username IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shift_exceptions_employee_id_date_unique');
        DB::statement('ALTER TABLE shift_exceptions ADD CONSTRAINT shift_exceptions_employee_id_date_unique UNIQUE (employee_id, date)');
        DB::statement('ALTER TABLE shift_templates DROP COLUMN IF EXISTS deleted_at');
        DB::statement('ALTER TABLE employee_shifts DROP COLUMN IF EXISTS deleted_at');
        DB::statement('ALTER TABLE shift_exceptions DROP COLUMN IF EXISTS deleted_at');
    }

    private function columnExists(string $table, string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->exists();
    }
};
