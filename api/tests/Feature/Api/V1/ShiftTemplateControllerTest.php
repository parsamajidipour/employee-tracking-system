<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShiftTemplate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_team_id(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        ShiftTemplate::factory()->create(['team_id' => $teamA->id, 'name' => 'Team A default']);
        ShiftTemplate::factory()->create(['team_id' => $teamB->id, 'name' => 'Team B default']);

        $response = $this->getJson("/api/v1/shift-templates?team_id={$teamA->id}");

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Team A default']);
    }

    public function test_store_creates_a_template(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $team = Team::factory()->create();

        $response = $this->postJson('/api/v1/shift-templates', [
            'team_id' => $team->id,
            'name' => 'Standard',
            'timezone' => 'Asia/Muscat',
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '07:00',
            'end_time' => '16:00',
            'grace_before_min' => 10,
            'grace_after_min' => 15,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('shift_templates', ['team_id' => $team->id, 'name' => 'Standard']);
    }

    public function test_store_rejects_a_day_of_week_out_of_range(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $team = Team::factory()->create();

        $response = $this->postJson('/api/v1/shift-templates', [
            'team_id' => $team->id,
            'name' => 'Standard',
            'timezone' => 'Asia/Muscat',
            'days_of_week' => [7],
            'start_time' => '07:00',
            'end_time' => '16:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['days_of_week.0']);
    }

    public function test_update_and_destroy(): void
    {
        $this->actingAs(User::factory()->hr()->create());
        $template = ShiftTemplate::factory()->create();

        $update = $this->putJson("/api/v1/shift-templates/{$template->id}", [
            'team_id' => $template->team_id,
            'name' => 'Renamed',
            'timezone' => 'Asia/Muscat',
            'days_of_week' => [0, 1, 2, 3, 4],
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);
        $update->assertOk();
        $this->assertDatabaseHas('shift_templates', ['id' => $template->id, 'name' => 'Renamed']);

        $destroy = $this->deleteJson("/api/v1/shift-templates/{$template->id}");
        $destroy->assertNoContent();
        $this->assertDatabaseMissing('shift_templates', ['id' => $template->id]);
    }
}
