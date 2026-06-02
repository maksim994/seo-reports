<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\ReportJobStatus;
use App\Models\Integration;
use App\Models\Project;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_portfolio_dashboard(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'name' => 'Client Site',
            'domain' => 'example.com',
            'has_analytics' => true,
        ]);

        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'metrika-token'],
            'status' => 'active',
        ]);

        $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => '12345',
            'external_resource_label' => 'example.com',
        ]);

        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'totals' => [1000, 800, 25.5, 120],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard?period_start=2026-04-01&period_end=2026-04-30');

        $response->assertOk()
            ->assertJsonPath('data.period.start', '2026-04-01')
            ->assertJsonPath('data.period.end', '2026-04-30')
            ->assertJsonPath('data.projects.0.name', 'Client Site')
            ->assertJsonPath('data.projects.0.metrics.metrika.visits', 1000)
            ->assertJsonPath('data.projects.0.metrics.metrika.users', 800)
            ->assertJsonPath('data.projects.0.metrics.metrika.bounce_rate', 25.5)
            ->assertJsonPath('data.projects.0.summary.work_items_count', 0)
            ->assertJsonPath('data.projects.0.summary.integrations_count', 1)
            ->assertJsonCount(1, 'data.projects');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
