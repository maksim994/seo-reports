<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\ReportJobStatus;
use App\Models\Integration;
use App\Models\Project;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\WorkItem;
use App\Enums\WorkItemCategory;
use App\ReportBlocks\ReportBlockRegistry;
use App\ReportBlocks\ReportRenderContext;
use App\Services\ReportBlockCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase15BlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_performed_block_renders_items_in_period(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        WorkItem::create([
            'project_id' => $project->id,
            'work_date' => '2026-04-10',
            'category' => WorkItemCategory::Seo,
            'description' => 'Аудит robots.txt',
        ]);
        WorkItem::create([
            'project_id' => $project->id,
            'work_date' => '2026-03-01',
            'category' => WorkItemCategory::Technical,
            'description' => 'Старый период',
        ]);

        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);
        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $context = new ReportRenderContext(
            $project->fresh(),
            $template,
            $job,
            collect(),
            app(ReportBlockCatalog::class),
        );

        $result = app(ReportBlockRegistry::class)->render('work_performed', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Аудит robots.txt', $result->html);
        $this->assertStringNotContainsString('Старый период', $result->html);
    }

    public function test_positions_visibility_block_renders_summary(): void
    {
        Http::fake([
            'api.topvisor.com/*' => Http::response([
                'result' => [
                    'visibility' => 42.5,
                    'visibility_dynamic' => 3.2,
                    'avg' => 12.4,
                    'tops' => ['top10' => 15],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::Topvisor,
            'credentials' => ['user_id' => '1', 'api_key' => 'secret', 'access_token' => 'secret'],
            'status' => 'active',
        ]);
        $binding = $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => '100:3',
            'external_resource_label' => 'Test · Yandex · Москва',
        ])->load('integration');

        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);
        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $context = new ReportRenderContext(
            $project,
            $template,
            $job,
            collect([$binding])->keyBy(fn ($b) => $b->integration->provider->value),
            app(ReportBlockCatalog::class),
        );

        $result = app(ReportBlockRegistry::class)->render('positions_visibility', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42.5', $result->html);
    }

    public function test_topvisor_api_key_connect_creates_integration(): void
    {
        Http::fake([
            'api.topvisor.com/*' => Http::response([
                'result' => [
                    ['id' => 100, 'name' => 'Demo', 'site' => 'demo.ru', 'searchers' => []],
                ],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/integrations/topvisor/api-key', [
                'user_id' => '67190',
                'api_key' => 'test-key',
            ])
            ->assertOk();

        $this->assertDatabaseHas('integrations', [
            'user_id' => $user->id,
            'provider' => IntegrationProvider::Topvisor->value,
        ]);
    }
}
