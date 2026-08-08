<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PositionsChannelTest extends TestCase
{
    use RefreshDatabase;

    private function authorize(): TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-positions',
            'socket_id' => '1234.5678',
        ]);
    }

    public function test_a_user_without_view_locations_is_refused_channel_authorization(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $this->authorize()->assertStatus(403);
    }

    public function test_an_employees_own_device_token_cannot_subscribe(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->authorize()->assertStatus(403);
    }

    public function test_a_supervisors_device_token_still_cannot_subscribe(): void
    {
        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->authorize()->assertStatus(403);
    }

    public function test_a_supervisor_session_can_subscribe(): void
    {
        $this->actingAs(User::factory()->supervisor()->create());

        $this->authorize()->assertOk();
    }

    public function test_an_unauthenticated_request_is_refused(): void
    {
        $this->authorize()->assertStatus(401);
    }
}
