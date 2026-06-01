<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/user/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/user/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'password123',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password-123', $user->password)
        );
    }

    public function test_user_cannot_change_password_without_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/user/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_user_cannot_use_existing_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user)
            ->patchJson('/api/user/profile', [
                'name' => $user->name,
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/user/profile', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
        ])->assertUnauthorized();
    }
}
