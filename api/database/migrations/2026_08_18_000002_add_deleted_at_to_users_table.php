<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('deleted_at')) {
            DB::statement('ALTER TABLE users ADD COLUMN deleted_at timestamp NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN IF EXISTS deleted_at');
    }

    private function columnExists(string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_name', 'users')
            ->where('column_name', $column)
            ->exists();
    }
};
