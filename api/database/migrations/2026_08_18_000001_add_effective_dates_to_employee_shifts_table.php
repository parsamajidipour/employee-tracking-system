<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('effective_from')) {
            DB::statement('ALTER TABLE employee_shifts ADD COLUMN effective_from timestamp NULL');
            DB::statement('UPDATE employee_shifts SET effective_from = created_at WHERE effective_from IS NULL');
            DB::statement('ALTER TABLE employee_shifts ALTER COLUMN effective_from SET NOT NULL');
        }

        if (! $this->columnExists('effective_to')) {
            DB::statement('ALTER TABLE employee_shifts ADD COLUMN effective_to timestamp NULL');
        }

        if ($this->constraintExists('employee_shifts_employee_id_template_id_unique')) {
            DB::statement('ALTER TABLE employee_shifts DROP CONSTRAINT employee_shifts_employee_id_template_id_unique');
        }

        DB::statement('CREATE INDEX IF NOT EXISTS employee_shifts_employee_id_effective_from_index ON employee_shifts (employee_id, effective_from)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS employee_shifts_employee_id_effective_from_index');
        DB::statement('ALTER TABLE employee_shifts DROP COLUMN IF EXISTS effective_to');
        DB::statement('ALTER TABLE employee_shifts DROP COLUMN IF EXISTS effective_from');

        if (! $this->constraintExists('employee_shifts_employee_id_template_id_unique')) {
            DB::statement('ALTER TABLE employee_shifts ADD CONSTRAINT employee_shifts_employee_id_template_id_unique UNIQUE (employee_id, template_id)');
        }
    }

    private function columnExists(string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_name', 'employee_shifts')
            ->where('column_name', $column)
            ->exists();
    }

    private function constraintExists(string $name): bool
    {
        return DB::table('pg_constraint')->where('conname', $name)->exists();
    }
};
