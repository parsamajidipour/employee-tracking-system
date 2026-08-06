<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * This deployment is a single company, not a multi-tenant one — see
 * DECISIONS.md's "Multi-team is deferred, not removed" entry. The `teams`
 * table and every employee's `team_id` stay exactly as designed (a team's
 * timezone still feeds App\Services\ShiftWindowResolver, shift_templates
 * still belong to a team); this migration just makes sure exactly one team
 * exists and every employee is on it, so the panel never needs to show a
 * team picker. A future multi-team deployment un-defers by adding the
 * picker back, not by re-doing this data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teamId = DB::table('teams')->orderBy('id')->value('id');

        if ($teamId === null) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => 'Default',
                'timezone' => 'Asia/Muscat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')->whereNull('team_id')->update(['team_id' => $teamId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deliberately irreversible: there is no recorded "before" state to
        // restore to (users with a null team_id before this ran are
        // indistinguishable from ones that happened to get this team_id on
        // purpose afterward). Rolling back would have to guess.
    }
};
