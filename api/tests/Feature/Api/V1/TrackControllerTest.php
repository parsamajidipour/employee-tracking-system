<?php

namespace Tests\Feature\Api\V1;

use App\Models\LocationPoint;
use App\Models\ShiftTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private CarbonImmutable $sunday;

    protected function setUp(): void
    {
        parent::setUp();
        ShiftTemplate::factory()->create();
        $this->employee = User::factory()->create();

        $this->sunday = CarbonImmutable::parse('next Sunday', 'Asia/Muscat')->startOfDay();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_batch_with_accepted_and_rejected_points_returns_202_with_counts(): void
    {
        Sanctum::actingAs($this->employee);

        $thursday = $this->sunday->addDays(4);
        CarbonImmutable::setTestNow($thursday->setTime(19, 0));

        $response = $this->postJson('/api/v1/track', [
            'points' => [
                [
                    'lat' => 23.5,
                    'lng' => 58.4,
                    'accuracy_m' => 5.0,
                    'speed_mps' => 0.0,
                    'heading_deg' => 0.0,
                    'battery_pct' => 80,
                    'is_mocked' => false,
                    'recorded_at' => $thursday->setTime(10, 0)->toISOString(),
                ],
                [
                    'lat' => 23.5,
                    'lng' => 58.4,
                    'accuracy_m' => 5.0,
                    'speed_mps' => 0.0,
                    'heading_deg' => 0.0,
                    'battery_pct' => 80,
                    'is_mocked' => false,
                    'recorded_at' => $thursday->setTime(18, 0)->toISOString(),
                ],
            ],
        ]);

        $response->assertStatus(202);
        $response->assertJson(['accepted' => 1, 'rejected' => 1]);
        $this->assertSame(1, LocationPoint::count());
    }

    public function test_malformed_payload_is_rejected_with_a_validation_error_not_a_202(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/track', [
            'points' => [
                [
                    'lat' => 200,
                    'lng' => 58.4,
                    'is_mocked' => false,
                    'recorded_at' => CarbonImmutable::now()->toISOString(),
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['points.0.lat']);
    }

    public function test_missing_points_key_is_rejected_with_a_validation_error(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/track', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['points']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/track', [
            'points' => [[
                'lat' => 23.5,
                'lng' => 58.4,
                'is_mocked' => false,
                'recorded_at' => CarbonImmutable::now()->toISOString(),
            ]],
        ]);

        $response->assertStatus(401);
    }
}
