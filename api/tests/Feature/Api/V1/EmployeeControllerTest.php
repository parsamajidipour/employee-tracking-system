<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Mail\EmployeePasswordChangedMail;
use App\Mail\EmployeeWelcomeMail;
use App\Models\Device;
use App\Models\ShiftTemplate;
use App\Models\TrackingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();

        $sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
        $thursday = $sunday->addDays(4);
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => $sunday->subMonth()]);

        $response = $this->getJson("/api/v1/employees/{$employee->id}/window?date={$thursday->toDateString()}");

        $response->assertOk();
        $response->assertJsonPath('window.source', 'employee_shift');
        $response->assertJsonPath('window.start', $thursday->setTime(7, 0)->utc()->toISOString());
    }

    public function test_window_is_null_on_a_weekend_day_under_a_sunday_thursday_template(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());
        $template = ShiftTemplate::factory()->create();
        $employee = User::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => now()->subMonth()]);

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
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'phone' => '+968 9000 0000',
            'email' => 'newhire@example.com',
            'password' => 'password123',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $employee = User::where('email', 'newhire@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame(UserRole::Employee, $employee->role);
        $this->assertTrue($employee->is_active);
    }

    public function test_store_requires_a_unique_email(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'phone' => '+968 9000 0001',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_store_requires_a_unique_phone(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());
        User::factory()->create(['phone' => '91112222']);

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'phone' => '91112222',
            'email' => 'unique@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_store_queues_a_welcome_email_with_the_password_and_download_link(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/employees', [
            'name' => 'New Hire',
            'phone' => '91223344',
            'email' => 'newhire2@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        Mail::assertQueued(EmployeeWelcomeMail::class, function (EmployeeWelcomeMail $mail) {
            return $mail->employee->email === 'newhire2@example.com'
                && $mail->plainPassword === 'password123'
                && $mail->hasTo('newhire2@example.com');
        });
    }

    public function test_update_changes_name_phone_and_email(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['name' => 'Old Name', 'phone' => '90000001', 'email' => 'old@example.com']);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'name' => 'New Name',
            'phone' => '90000002',
            'email' => 'new@example.com',
        ]);

        $response->assertOk();
        $employee->refresh();
        $this->assertSame('New Name', $employee->name);
        $this->assertSame('90000002', $employee->phone);
        $this->assertSame('new@example.com', $employee->email);
    }

    public function test_update_rejects_an_email_already_used_by_another_employee(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        User::factory()->create(['email' => 'taken@example.com']);
        $employee = User::factory()->create(['email' => 'mine@example.com']);

        $response = $this->putJson("/api/v1/employees/{$employee->id}", [
            'name' => $employee->name,
            'phone' => $employee->phone,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
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
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create();

        $response = $this->putJson("/api/v1/employees/{$employee->id}/password", [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertNoContent();
        $this->assertTrue(Hash::check('brand-new-password', $employee->refresh()->password));
    }

    public function test_reset_password_rejects_a_mismatched_confirmation(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create();
        $originalPassword = $employee->password;

        $response = $this->putJson("/api/v1/employees/{$employee->id}/password", [
            'password' => 'brand-new-password',
            'password_confirmation' => 'something-else',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
        $this->assertSame($originalPassword, $employee->refresh()->password);
    }

    public function test_reset_password_queues_a_password_changed_email(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['email' => 'alice@example.com']);

        $this->putJson("/api/v1/employees/{$employee->id}/password", [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertNoContent();

        Mail::assertQueued(EmployeePasswordChangedMail::class, function (EmployeePasswordChangedMail $mail) {
            return $mail->plainPassword === 'brand-new-password' && $mail->hasTo('alice@example.com');
        });
    }

    public function test_reset_password_skips_the_email_when_the_employee_has_none_on_file(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['email' => null]);

        $this->putJson("/api/v1/employees/{$employee->id}/password", [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertNoContent();

        Mail::assertNotQueued(EmployeePasswordChangedMail::class);
    }

    public function test_revoke_device_deletes_the_token_and_marks_the_device_revoked(): void
    {
        Cache::flush();
        $this->actingAs(User::factory()->hr()->create());
        $employee = User::factory()->create(['email' => 'alice@example.com', 'password' => Hash::make('secret123')]);

        $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
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
        $employee = User::factory()->create([
            'is_active' => true,
            'email' => 'gone@example.com',
            'phone' => '91119999',
            'password' => Hash::make('secret123'),
        ]);
        $template = ShiftTemplate::factory()->create();
        $employee->employeeShifts()->create(['template_id' => $template->id, 'effective_from' => now()]);

        $this->postJson('/api/v1/device/login', [
            'identifier' => 'gone@example.com',
            'password' => 'secret123',
            'device_identifier' => 'device-1',
        ])->assertOk();

        $response = $this->deleteJson("/api/v1/employees/{$employee->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $employee->id]);
        $this->assertFalse($employee->refresh()->is_active);
        $this->assertDatabaseCount('employee_shifts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotSame('gone@example.com', $employee->email);
        $this->assertNotSame('91119999', $employee->phone);

        $index = $this->getJson('/api/v1/employees');
        $index->assertOk();
        $index->assertJsonMissing(['id' => $employee->id]);

        Mail::fake();
        $reuse = $this->postJson('/api/v1/employees', [
            'name' => 'New Gone',
            'phone' => '91119999',
            'email' => 'gone@example.com',
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
