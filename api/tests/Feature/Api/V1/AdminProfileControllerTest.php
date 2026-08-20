<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private function asStatefulFrontend(): static
    {
        return $this->withHeader('Referer', 'http://'.config('sanctum.stateful')[0].'/');
    }

    public function test_updating_name_and_email_does_not_require_a_password(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk();
        $response->assertJson(['name' => 'New Name', 'email' => 'new@example.com']);
        $this->assertSame('New Name', $admin->fresh()->name);
        $this->assertSame('new@example.com', $admin->fresh()->email);
    }

    public function test_profile_update_rejects_an_email_already_used_by_another_admin(): void
    {
        User::factory()->admin()->create(['email' => 'taken@example.com']);
        $admin = User::factory()->admin()->create(['email' => 'mine@example.com']);
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/profile', [
            'name' => $admin->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_profile_update_does_not_accept_password_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $originalPassword = $admin->password;
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/profile', [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => 'IgnoredPassword1',
            'password_confirmation' => 'IgnoredPassword1',
        ]);

        $response->assertOk();
        $this->assertSame($originalPassword, $admin->fresh()->password);
    }

    public function test_password_update_with_correct_current_password_succeeds(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->asStatefulFrontend()->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPassword123', $admin->fresh()->password));
    }

    public function test_password_update_with_wrong_current_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $originalPassword = $admin->password;
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/password', [
            'current_password' => 'not-the-password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
        $this->assertSame($originalPassword, $admin->fresh()->password);
    }

    public function test_password_update_requires_confirmation_to_match(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'SomethingElse123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_update_rejects_a_password_shorter_than_ten_characters(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'Short1',
            'password_confirmation' => 'Short1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_update_does_not_touch_name_or_email(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Keep Me', 'email' => 'keep@example.com']);
        $this->actingAs($admin);

        $response = $this->asStatefulFrontend()->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk();
        $this->assertSame('Keep Me', $admin->fresh()->name);
        $this->assertSame('keep@example.com', $admin->fresh()->email);
    }

    public function test_non_admin_cannot_update_admin_profile_or_password(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        $this->putJson('/api/v1/admin/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->putJson('/api/v1/admin/profile', ['name' => 'X', 'email' => 'x@example.com'])->assertStatus(401);
        $this->putJson('/api/v1/admin/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertStatus(401);
    }
}
