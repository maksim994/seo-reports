<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_bind_integration_resource_to_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['domain' => 'example.com']);
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'token'],
            'status' => 'active',
        ]);

        Http::fake([
            'api-metrika.yandex.net/*' => Http::response(['counters' => []]),
        ]);

        $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/integrations", [
                'integration_id' => $integration->id,
                'external_resource_id' => '12345',
                'external_resource_label' => 'example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.external_resource_id', '12345');

        $this->assertDatabaseHas('project_integrations', [
            'project_id' => $project->id,
            'integration_id' => $integration->id,
            'external_resource_id' => '12345',
        ]);

        $this->assertTrue($project->fresh()->has_analytics);
    }

    public function test_user_cannot_bind_foreign_integration(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $integration = Integration::create([
            'user_id' => $other->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'token'],
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/integrations", [
                'integration_id' => $integration->id,
                'external_resource_id' => '12345',
            ])
            ->assertForbidden();
    }
}
