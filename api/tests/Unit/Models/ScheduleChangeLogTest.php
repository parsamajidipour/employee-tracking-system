<?php

namespace Tests\Unit\Models;

use App\Models\ScheduleChangeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ScheduleChangeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_throws(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create();
        $log = ScheduleChangeLog::create([
            'actor_id' => $actor->id,
            'target_employee_id' => $employee->id,
            'before' => null,
            'after' => ['type' => 'leave'],
        ]);

        $this->expectException(LogicException::class);
        $log->update(['reason' => 'trying to rewrite history']);
    }

    public function test_delete_throws(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create();
        $log = ScheduleChangeLog::create([
            'actor_id' => $actor->id,
            'target_employee_id' => $employee->id,
            'before' => null,
            'after' => ['type' => 'leave'],
        ]);

        $this->expectException(LogicException::class);
        $log->delete();
    }
}
