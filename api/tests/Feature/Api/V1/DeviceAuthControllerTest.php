<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Device;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function makeEmployee(array $overrides = []): User
    {
        return User::factory()->create([
            'email' => 'alice@example.com',
            'phone' => '91234567',
            'password' => Hash::make('correct-password'),
            'is_active' => true,
            ...$overrides,
        ]);
    }

    public function test_login_with_email_issues_a_working_token(): void
    {
        $this->makeEmployee();

        $response = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
            'device_name' => "Alice's phone",
        ]);

        $response->assertOk();
        $token = $response->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->postJson('/api/v1/track', ['points' => []])
            ->assertStatus(422);
    }

    public function test_login_with_phone_issues_a_working_token(): void
    {
        $this->makeEmployee();

        $response = $this->postJson('/api/v1/device/login', [
            'identifier' => '91234567',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_wrong_password_is_refused(): void
    {
        $this->makeEmployee();

        $response = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'wrong-password',
            'device_identifier' => 'device-1',
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, Device::count());
    }

    public function test_a_second_device_is_refused_while_the_first_is_active(): void
    {
        $this->makeEmployee();

        $first = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);
        $first->assertOk();

        $second = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-2',
        ]);

        $second->assertStatus(409);
        $this->assertSame(1, Device::count());
    }

    public function test_an_inactive_employee_cannot_log_in(): void
    {
        $this->makeEmployee(['is_active' => false]);

        $response = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);

        $response->assertStatus(403);
        $this->assertSame(0, Device::count());
    }

    public function test_a_non_employee_role_cannot_log_in_even_with_a_matching_identifier(): void
    {
        $this->makeEmployee(['role' => UserRole::Supervisor]);

        $response = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_revoked_token_gets_401_on_track(): void
    {
        $employee = $this->makeEmployee();

        $login = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);
        $token = $login->json('token');

        app(DeviceService::class)->revoke($employee->activeDevice);

        $this->withToken($token)
            ->postJson('/api/v1/track', ['points' => []])
            ->assertStatus(401);
    }

    public function test_last_seen_at_updates_on_track(): void
    {
        $this->makeEmployee();

        $login = $this->postJson('/api/v1/device/login', [
            'identifier' => 'alice@example.com',
            'password' => 'correct-password',
            'device_identifier' => 'device-1',
        ]);
        $token = $login->json('token');
        $device = Device::first();
        $this->assertNull($device->last_seen_at);

        $this->withToken($token)->postJson('/api/v1/track', [
            'points' => [[
                'lat' => 23.5,
                'lng' => 58.4,
                'is_mocked' => false,
                'recorded_at' => now()->toISOString(),
            ]],
        ])->assertStatus(202);

        $this->assertNotNull($device->refresh()->last_seen_at);
    }
}
