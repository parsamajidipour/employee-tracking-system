<?php

namespace Tests\Feature\Services;

use App\Models\InspectionCase;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Services\CaseAssignmentService;
use App\Services\CaseLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CaseAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::connection()->flushdb();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function onShiftNow(): CarbonImmutable
    {
        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();

        return $sunday->setTime(10, 0);
    }

    private function putOnShift(User $employee, CarbonImmutable $now): void
    {
        $template = ShiftTemplate::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $now->subMonth()]);
    }

    private function seedLastKnown(User $employee, float $lat, float $lng, CarbonImmutable $recordedAt): void
    {
        Redis::setex("last_known:{$employee->id}", 3600, json_encode([
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'lat' => $lat,
            'lng' => $lng,
            'recorded_at' => $recordedAt->toISOString(),
        ]));
    }

    public function test_ranks_the_nearest_on_shift_employee_first(): void
    {
        $now = $this->onShiftNow();
        CarbonImmutable::setTestNow($now);

        $near = User::factory()->create(['name' => 'Near']);
        $far = User::factory()->create(['name' => 'Far']);
        $this->putOnShift($near, $now);
        $this->putOnShift($far, $now);

        $caseLat = 23.55;
        $caseLng = 58.35;

        $this->seedLastKnown($near, 23.5501, 58.3501, $now);
        $this->seedLastKnown($far, 23.9, 58.9, $now);

        $admin = User::factory()->admin()->create();
        $case = app(CaseLifecycleService::class)->create([
            'reference_no' => 'INS-1',
            'title' => 'Test',
            'property_address' => null,
            'lat' => $caseLat,
            'lng' => $caseLng,
            'priority' => 'normal',
        ], $admin);

        $ranked = app(CaseAssignmentService::class)->rank(
            InspectionCase::query()->withLatLng()->findOrFail($case->id),
        );

        $this->assertSame($near->id, $ranked->first()->employeeId);
        $this->assertSame($far->id, $ranked->last()->employeeId);
        $this->assertLessThan($ranked->last()->distanceM, $ranked->first()->distanceM);
    }

    public function test_excludes_employees_outside_their_shift_window(): void
    {
        $now = $this->onShiftNow();
        CarbonImmutable::setTestNow($now);

        $onShift = User::factory()->create();
        $offShift = User::factory()->create();
        $this->putOnShift($onShift, $now);

        $this->seedLastKnown($onShift, 23.55, 58.35, $now);
        $this->seedLastKnown($offShift, 23.55, 58.35, $now);

        $admin = User::factory()->admin()->create();
        $case = app(CaseLifecycleService::class)->create([
            'reference_no' => 'INS-2',
            'title' => 'Test',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $ranked = app(CaseAssignmentService::class)->rank(
            InspectionCase::query()->withLatLng()->findOrFail($case->id),
        );

        $ids = $ranked->pluck('employeeId')->all();
        $this->assertContains($onShift->id, $ids);
        $this->assertNotContains($offShift->id, $ids);
    }

    public function test_workload_tie_breaks_equal_distance_candidates(): void
    {
        $now = $this->onShiftNow();
        CarbonImmutable::setTestNow($now);

        $busy = User::factory()->create();
        $free = User::factory()->create();
        $this->putOnShift($busy, $now);
        $this->putOnShift($free, $now);

        $this->seedLastKnown($busy, 23.55, 58.35, $now);
        $this->seedLastKnown($free, 23.55, 58.35, $now);

        $admin = User::factory()->admin()->create();
        $lifecycle = app(CaseLifecycleService::class);

        $existing = $lifecycle->create([
            'reference_no' => 'INS-3',
            'title' => 'Existing',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);
        $lifecycle->assign($existing, $busy, $admin);

        $newCase = $lifecycle->create([
            'reference_no' => 'INS-4',
            'title' => 'New',
            'property_address' => null,
            'lat' => 23.55,
            'lng' => 58.35,
            'priority' => 'normal',
        ], $admin);

        $ranked = app(CaseAssignmentService::class)->rank(
            InspectionCase::query()->withLatLng()->findOrFail($newCase->id),
        );

        $this->assertSame($free->id, $ranked->first()->employeeId);
    }
}
