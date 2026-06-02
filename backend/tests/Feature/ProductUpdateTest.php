<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProductUpdateRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/product-updates')
            ->assertUnauthorized();
    }

    public function test_lists_active_updates_with_unread_count(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/product-updates')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'updates',
                    'unread_count',
                ],
            ])
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.updates.0.id', 'project-analytics-dashboard')
            ->assertJsonPath('data.updates.0.is_read', false);
    }

    public function test_dismiss_marks_update_as_read(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/product-updates/project-analytics-dashboard/dismiss')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.updates.0.is_read', true);

        $this->assertDatabaseHas('user_product_update_reads', [
            'user_id' => $user->id,
            'update_id' => 'project-analytics-dashboard',
        ]);
    }

    public function test_dismiss_all_marks_every_active_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/product-updates/dismiss-all')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0);

        $this->assertGreaterThanOrEqual(
            1,
            UserProductUpdateRead::query()->where('user_id', $user->id)->count(),
        );
    }

    public function test_dismiss_unknown_update_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/product-updates/non-existent/dismiss')
            ->assertNotFound();
    }
}
