<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_admin_can_update_user_flags(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$user->id}", [
                'is_admin' => true,
                'is_blocked' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_admin', true);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}", ['is_admin' => false])
            ->assertStatus(422);
    }

    public function test_blocked_user_cannot_login(): void
    {
        User::factory()->blocked()->create([
            'email' => 'blocked@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'blocked@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_registration_disabled_blocks_register(): void
    {
        app(SettingsService::class)->setMany(['registration_enabled' => false]);

        $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422);
    }

    public function test_admin_can_update_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/api/admin/settings', [
                'app_name' => 'My SEO Reports',
                'registration_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'My SEO Reports')
            ->assertJsonPath('data.registration_enabled', false);
    }

    public function test_public_settings_endpoint(): void
    {
        app(SettingsService::class)->setMany(['app_name' => 'Public Name']);

        $this->getJson('/api/settings/public')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Public Name')
            ->assertJsonStructure([
                'data' => ['app_name', 'registration_enabled', 'maintenance_mode', 'maintenance_message'],
            ]);
    }
}
