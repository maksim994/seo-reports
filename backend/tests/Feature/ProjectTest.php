<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_crud_projects(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'My Site',
            'domain' => 'example.com',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'My Site');

        $projectId = $create->json('data.id');

        $this->actingAs($user)->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user)->putJson("/api/projects/{$projectId}", [
            'name' => 'Updated Site',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Site');

        $this->actingAs($user)->deleteJson("/api/projects/{$projectId}")
            ->assertNoContent();
    }
}
