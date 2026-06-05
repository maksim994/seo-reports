<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Models\Project;
use App\Models\User;
use App\Services\ReportBlockCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_analytics_dashboard_config(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/analytics-dashboard");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'widgets',
                    'is_suggested',
                    'catalog' => ['blocks', 'categories'],
                ],
            ]);
    }

    public function test_user_can_save_analytics_dashboard_widgets(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $payload = [
            'widgets' => [
                [
                    'id' => 'widget-1',
                    'block_type' => 'metrika_overview',
                    'settings' => [],
                    'layout' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                ],
            ],
        ];

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}/analytics-dashboard", $payload);

        $response->assertOk()
            ->assertJsonPath('data.is_suggested', false)
            ->assertJsonPath('data.widgets.0.block_type', 'metrika_overview');

        $project->refresh();
        $this->assertSame('widget-1', $project->settings['analytics_dashboard']['widgets'][0]['id']);
    }

    public function test_save_rejects_layout_outside_grid(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->putJson("/api/projects/{$project->id}/analytics-dashboard", [
            'widgets' => [
                [
                    'id' => 'w1',
                    'block_type' => 'metrika_overview',
                    'settings' => [],
                    'layout' => ['x' => 10, 'y' => 0, 'w' => 6, 'h' => 4],
                ],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_access_other_users_dashboard(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->getJson("/api/projects/{$project->id}/analytics-dashboard")
            ->assertForbidden();
    }

    public function test_dashboard_data_returns_widget_payload_structure(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'settings' => [
                'analytics_dashboard' => [
                    'widgets' => [
                        [
                            'id' => 'w-metrika',
                            'block_type' => 'metrika_overview',
                            'settings' => [],
                            'layout' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4],
                        ],
                    ],
                ],
            ],
        ]);

        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'token'],
            'status' => IntegrationStatus::Active,
        ]);

        $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => '12345',
            'external_resource_label' => 'example.com',
        ]);

        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'totals' => [500, 400, 30.0, 90],
            ]),
        ]);

        $response = $this->actingAs($user)->postJson("/api/projects/{$project->id}/analytics-dashboard/data", [
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.period.start', '2026-04-01')
            ->assertJsonPath('data.widgets.0.id', 'w-metrika')
            ->assertJsonPath('data.widgets.0.block_type', 'metrika_overview')
            ->assertJsonPath('data.widgets.0.chart_title', 'Динамика ключевых метрик')
            ->assertJsonStructure(['data' => ['widgets' => [['title', 'chart_title', 'success', 'html']]]]);

        $html = (string) $response->json('data.widgets.0.html');
        $this->assertStringNotContainsString('class="muted"', $html);
        $this->assertStringNotContainsString('chart-title', $html);
    }

    public function test_dashboard_blocks_excludes_general_blocks(): void
    {
        $catalog = app(ReportBlockCatalog::class);
        $types = array_column($catalog->dashboardBlocks(), 'block_type');

        $this->assertNotContains('title_page', $types);
        $this->assertNotContains('text_block', $types);
        $this->assertContains('metrika_overview', $types);
    }

    public function test_metrika_project_suggestions_include_search_engines_timeline_for_25_months(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexMetrika,
            'credentials' => ['access_token' => 'token'],
            'status' => IntegrationStatus::Active,
        ]);
        $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => '12345',
        ]);

        $response = $this->actingAs($user)->getJson("/api/projects/{$project->id}/analytics-dashboard");

        $response->assertOk();
        $widgets = $response->json('data.widgets');
        $timeline = collect($widgets)->firstWhere('block_type', 'metrika_search_engines_timeline');
        $pageGroups = collect($widgets)->firstWhere('block_type', 'metrika_page_groups');
        $pageGroupConversions = collect($widgets)->firstWhere('block_type', 'metrika_page_group_conversions');

        $this->assertNotNull($timeline);
        $this->assertSame('25_months', $timeline['settings']['chart_period'] ?? null);
        $this->assertNotNull($pageGroups);
        $this->assertSame('organic', $pageGroups['settings']['traffic_scope'] ?? null);
        $this->assertNotNull($pageGroupConversions);
        $this->assertSame('organic', $pageGroupConversions['settings']['traffic_scope'] ?? null);
    }
}
