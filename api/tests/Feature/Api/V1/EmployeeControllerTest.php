<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\ShiftTemplate;
use App\Models\TrackingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_employees(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['name' => 'Fahad Al-Balushi']);

        $response = $this->getJson('/api/v1/employees');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $employee->id, 'name' => 'Fahad Al-Balushi']);
    }

    public function test_window_returns_the_resolved_window_for_the_chosen_date(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        ShiftTemplate::factory()->create();
        $employee = User::factory()->create();

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/window?date={$thursday->toDateString()}");

        $response->assertOk();
        $response->assertJsonPath('window.source', 'default_template');
        $response->assertJsonPath('window.start', $thursday->setTime(7, 0)->utc()->toISOString());
    }

    public function test_window_is_null_on_a_weekend_day_under_a_sunday_thursday_template(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        ShiftTemplate::factory()->create();
        $employee = User::factory()->create();

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $friday = $sunday->addDays(5);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/window?date={$friday->toDateString()}");

        $response->assertOk();
        $response->assertJson(['window' => null]);
    }

    public function test_session_returns_the_open_sessions_started_at(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $employee = User::factory()->create();
        $startedAt = CarbonImmutable::now()->subHours(2)->startOfSecond();
        TrackingSession::factory()->create(['employee_id' => $employee->id, 'started_at' => $startedAt]);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/session");

        $response->assertOk();
        $response->assertJsonPath('started_at', $startedAt->toISOString());
    }

    public function test_session_returns_null_when_no_session_is_open(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $employee = User::factory()->create();
        TrackingSession::factory()->create([
            'employee_id' => $employee->id,
            'ended_at' => CarbonImmutable::now(),
        ]);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/session");

        $response->assertOk();
        $response->assertJson(['started_at' => null]);
    }

    public function test_store_creates_an_active_employee(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'phone' => '+968 9000 0000',
            'username' => 'newhire',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $employee = User::where('username', 'newhire')->first();
        $this->assertNotNull($employee);
        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertTrue($employee->is_active);
    }

    public function test_store_requires_a_unique_username(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        User::factory()->create(['username' => 'taken']);

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'username' => 'taken',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username']);
    }

    public function test_set_active_toggles_the_flag(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['is_active' => true]);

        $response = $this->putJson("/api/v1/employees/{$employee->id}/active", ['is_active' => false]);

        $response->assertOk();
        $this->assertFalse($employee->refresh()->is_active);
    }

    public function test_reset_password_changes_the_stored_hash(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create();

        $response = $this->putJson("/api/v1/employees/{$employee->id}/password", ['password' => 'brand-new-password']);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('brand-new-password', $employee->refresh()->password));
    }

    public function test_revoke_device_deletes_the_token_and_marks_the_device_revoked(): void
    {
        Cache::flush();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['username' => 'alice', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/v1/device/login', [
            'username' => 'alice',
            'password' => 'secret123',
            'device_identifier' => 'device-1',
        ])->assertOk();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}/device");

        $response->assertNoContent();
        $device = Device::where('employee_id', $employee->id)->first();
        $this->assertNotNull($device->revoked_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_revoke_device_is_a_no_op_when_there_is_no_active_device(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}/device");

        $response->assertNoContent();
    }

    public function test_destroy_soft_deletes_the_employee_and_removes_it_from_the_index(): void
    {
        Cache::flush();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['is_active' => true, 'username' => 'gone', 'password' => Hash::make('secret123')]);
        $template = ShiftTemplate::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => now()]);

        $this->postJson('/api/v1/device/login', [
            'username' => 'gone',
            'password' => 'secret123',
            'device_identifier' => 'device-1',
        ])->assertOk();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertFalse($employee->refresh()->is_active);
        $this->assertDatabaseCount('employee_shifts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertStringEndsWith('_parsa', $employee->username);
        $this->assertNotSame('gone', $employee->username);

        $index = $this->getJson('/api/v1/employees');
        $index->assertOk();
        $index->assertJsonMissing(['id' => $employee->id]);

        $reuse = $this->postJson('/api/v1/employees', [
            'name' => 'New Gone',
            'username' => 'gone',
            'password' => 'password123',
        ]);
        $reuse->assertCreated();
    }

    public function test_destroy_rejects_a_non_employee_role(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $supervisor = User::factory()->supervisor()->create();

        $response = $this->deleteJson("/api/v1/employees/{$supervisor->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $supervisor->id, 'deleted_at' => null]);
    }
}
