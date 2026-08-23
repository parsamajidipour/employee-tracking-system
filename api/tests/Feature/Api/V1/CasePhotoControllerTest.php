<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\CaseLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CasePhotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakePngUpload(string $name = 'site.png'): UploadedFile
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $path = tempnam(sys_get_temp_dir(), 'case-photo').'.png';
        file_put_contents($path, $pngBytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function makeAssignedCase(float $lat, float $lng): array
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $lifecycle = app(CaseLifecycleService::class);
        $case = $lifecycle->create([
            'reference_no' => 'INS-'.uniqid(),
            'title' => 'Villa valuation',
            'property_address' => null,
            'lat' => $lat,
            'lng' => $lng,
            'priority' => 'normal',
        ], $admin);
        $case = $lifecycle->assign($case, $employee, $admin);

        return [$case, $employee];
    }

    public function test_photo_within_radius_is_marked_gps_verified(): void
    {
        Storage::fake('local');
        [$case, $employee] = $this->makeAssignedCase(23.55, 58.35);

        $this->actingAs($employee);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/photos", [
            'photo' => $this->fakePngUpload(),
            'lat' => 23.5501,
            'lng' => 58.3501,
            'accuracy_m' => 8.0,
            'captured_at' => now()->toISOString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('is_gps_verified', true);
    }

    public function test_photo_far_from_the_case_location_is_flagged_not_gps_verified(): void
    {
        Storage::fake('local');
        [$case, $employee] = $this->makeAssignedCase(23.55, 58.35);

        $this->actingAs($employee);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/photos", [
            'photo' => $this->fakePngUpload(),
            'lat' => 23.65,
            'lng' => 58.45,
            'captured_at' => now()->toISOString(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('is_gps_verified', false);
        $this->assertGreaterThan(150, $response->json('distance_from_case_m'));
    }

    public function test_only_the_assigned_employee_can_upload_a_photo(): void
    {
        Storage::fake('local');
        [$case] = $this->makeAssignedCase(23.55, 58.35);
        $intruder = User::factory()->create();

        $this->actingAs($intruder);
        $response = $this->postJson("/api/v1/me/cases/{$case->id}/photos", [
            'photo' => $this->fakePngUpload(),
            'lat' => 23.55,
            'lng' => 58.35,
            'captured_at' => now()->toISOString(),
        ]);

        $response->assertForbidden();
    }
}
