<?php

namespace Tests\Feature\Api\V1;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_teams(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        Team::factory()->create(['name' => 'Muscat Field Team']);

        $response = $this->getJson('/api/v1/teams');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Muscat Field Team']);
    }

    public function test_store_creates_a_team(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/teams', [
            'name' => 'Sohar Field Team',
            'timezone' => 'Asia/Muscat',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('teams', ['name' => 'Sohar Field Team', 'timezone' => 'Asia/Muscat']);
    }

    public function test_store_rejects_an_invalid_timezone(): void
    {
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/teams', [
            'name' => 'Sohar Field Team',
            'timezone' => 'Not/AZone',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['timezone']);
    }

    public function test_update_modifies_a_team(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $team = Team::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'New Name',
            'timezone' => 'Asia/Muscat',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
    }

    public function test_destroy_removes_a_team(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $team = Team::factory()->create();

        $response = $this->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/teams');

        $response->assertStatus(401);
    }
}
