<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingInterruptionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function putOnShift(User $employee, CarbonImmutable $now): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $now->subMonth()]);
    }

    public function test_interruption_is_recorded_while_on_shift(): void
    {
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $now = $sunday->setTime(10, 0);
        CarbonImmutable::setTestNow($now);

        $employee = User::factory()->create();
        $this->putOnShift($employee, $now);

        $this->actingAs($employee);
        $response = $this->postJson('/api/v1/tracking-interruptions/start', [
            'reason' => 'gps_disabled',
            'at' => $now->toISOString(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('accepted', true);
        $this->assertDatabaseHas('tracking_interruptions', [
            'employee_id' => $employee->id,
            'reason' => 'gps_disabled',
        ]);

        $stopResponse = $this->postJson('/api/v1/tracking-interruptions/stop', [
            'at' => $now->addMinutes(5)->toISOString(),
        ]);

        $stopResponse->assertOk();
        $this->assertDatabaseMissing('tracking_interruptions', [
            'employee_id' => $employee->id,
            'ended_at' => null,
        ]);
    }

    public function test_interruption_outside_a_shift_window_is_not_recorded(): void
    {
        $employee = User::factory()->create();
        $now = CarbonImmutable::now();

        $this->actingAs($employee);
        $response = $this->postJson('/api/v1/tracking-interruptions/start', [
            'reason' => 'flight_mode',
            'at' => $now->toISOString(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('accepted', false);
        $this->assertDatabaseCount('tracking_interruptions', 0);
    }
}
