<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The `positions` private channel's authorization callback (routes/
 * channels.php), reached via POST /broadcasting/auth — see bootstrap/
 * app.php's withBroadcasting() call for why that route needs auth:sanctum
 * (not the framework's ['web']-only default) for $user->currentAccessToken()
 * to be meaningful here at all.
 */
class PositionsChannelTest extends TestCase
{
    use RefreshDatabase;

    private function authorize(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-positions',
            'socket_id' => '1234.5678',
        ]);
    }

    public function test_a_user_without_view_locations_is_refused_channel_authorization(): void
    {
        // hr has manage-schedules, not view-locations.
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
        // Proves the rejection is because it's a device token, not because
        // of role — this supervisor has view-locations, but a mobile token
        // must be refused here the same way EnsureCapability refuses it on
        // GET /api/v1/positions.
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
        // auth:sanctum rejects this before the channel closure ever runs —
        // 401, not 403, same distinction AuthorizationTest makes for the
        // HTTP routes.
        $this->authorize()->assertStatus(401);
    }
}
