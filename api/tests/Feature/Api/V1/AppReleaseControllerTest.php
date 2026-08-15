<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppReleaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_version_returns_the_highest_version_code(): void
    {
        AppRelease::factory()->create(['version_code' => 1, 'version_name' => '1.0.0']);
        AppRelease::factory()->create(['version_code' => 3, 'version_name' => '1.2.0']);
        AppRelease::factory()->create(['version_code' => 2, 'version_name' => '1.1.0']);

        $response = $this->getJson('/api/v1/app/latest-version');

        $response->assertOk();
        $response->assertJsonPath('version_code', 3);
        $response->assertJsonPath('version_name', '1.2.0');
    }

    public function test_latest_version_is_404_when_no_release_exists(): void
    {
        $response = $this->getJson('/api/v1/app/latest-version');

        $response->assertStatus(404);
    }

    public function test_latest_version_does_not_require_authentication(): void
    {
        AppRelease::factory()->create(['version_code' => 1]);

        $response = $this->getJson('/api/v1/app/latest-version');

        $response->assertOk();
    }

    public function test_only_admin_can_upload_a_release(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->hr()->create());

        $response = $this->postJson('/api/v1/app-releases', [
            'apk' => UploadedFile::fake()->create('app.apk', 100),
            'version_code' => 5,
            'version_name' => '1.3.0',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_upload_a_release(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/app-releases', [
            'apk' => UploadedFile::fake()->create('app.apk', 100),
            'version_code' => 5,
            'version_name' => '1.3.0',
            'release_notes' => 'Bug fixes.',
            'is_mandatory' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('app_releases', ['version_code' => 5, 'version_name' => '1.3.0']);
        Storage::disk('local')->assertExists('releases/app-v5.apk');
    }

    public function test_upload_rejects_a_duplicate_version_code(): void
    {
        Storage::fake('local');
        AppRelease::factory()->create(['version_code' => 5]);
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/app-releases', [
            'apk' => UploadedFile::fake()->create('app.apk', 100),
            'version_code' => 5,
            'version_name' => '1.3.0',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['version_code']);
    }

    public function test_upload_rejects_a_non_apk_file(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/v1/app-releases', [
            'apk' => UploadedFile::fake()->create('app.txt', 100),
            'version_code' => 5,
            'version_name' => '1.3.0',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['apk']);
    }

    public function test_admin_can_delete_a_release(): void
    {
        Storage::fake('local');
        $release = AppRelease::factory()->create(['file_path' => 'releases/app-v5.apk']);
        Storage::disk('local')->put('releases/app-v5.apk', 'binary');
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->deleteJson("/api/v1/app-releases/{$release->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('app_releases', ['id' => $release->id]);
        Storage::disk('local')->assertMissing('releases/app-v5.apk');
    }

    public function test_download_streams_the_apk_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('releases/app-v1.apk', 'binary-content');
        $release = AppRelease::factory()->create(['file_path' => 'releases/app-v1.apk']);

        $response = $this->get("/api/app-releases/{$release->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.android.package-archive');
    }

    public function test_download_reports_service_unavailable_when_file_is_missing(): void
    {
        Storage::fake('local');
        $release = AppRelease::factory()->create(['file_path' => 'releases/missing.apk']);

        $response = $this->get("/api/app-releases/{$release->id}/download");

        $response->assertStatus(503);
    }
}
