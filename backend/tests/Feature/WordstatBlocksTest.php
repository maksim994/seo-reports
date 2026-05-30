<?php

namespace Tests\Feature;

use App\Enums\IntegrationProvider;
use App\Enums\ReportJobStatus;
use App\Models\Integration;
use App\Models\Project;
use App\Models\ReportTemplate;
use App\Models\User;
use App\ReportBlocks\ReportBlockRegistry;
use App\ReportBlocks\ReportRenderContext;
use App\Services\ReportBlockCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordstatBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_wordstat_dynamics_block_renders_series(): void
    {
        Http::fake([
            'api.wordstat.yandex.net/v1/dynamics' => Http::response([
                'dynamics' => [
                    ['date' => '2024-03-01', 'count' => 45000, 'share' => 0.4],
                    ['date' => '2024-04-01', 'count' => 42000, 'share' => 0.38],
                ],
            ]),
        ]);

        $context = $this->makeContext();

        $result = app(ReportBlockRegistry::class)->render('wordstat_dynamics', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('осевой вентилятор', $result->html);
        $this->assertStringContainsString('центробежный вентилятор', $result->html);
        $this->assertStringContainsString('45 000', $result->html);
    }

    public function test_wordstat_dynamics_requires_phrases(): void
    {
        $context = $this->makeContext(withPhrases: false);

        $result = app(ReportBlockRegistry::class)->render('wordstat_dynamics', $context, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('настройках проекта', $result->html);
    }

    private function makeContext(bool $withPhrases = true): ReportRenderContext
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::YandexWordstat,
            'credentials' => ['access_token' => 'wordstat-token'],
            'status' => 'active',
        ]);
        $binding = $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => 'default',
            'external_resource_label' => 'Яндекс Вордстат',
            'config' => $withPhrases ? [
                'wordstat' => [
                    'dynamics' => [
                        'phrases' => "осевой вентилятор\nцентробежный вентилятор",
                        'period' => 'monthly',
                        'lookback_months' => 24,
                    ],
                ],
            ] : null,
        ])->load('integration');

        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);
        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-03-26',
            'period_end' => '2026-04-25',
        ]);

        return new ReportRenderContext(
            $project->fresh(),
            $template,
            $job,
            collect([IntegrationProvider::YandexWordstat->value => $binding]),
            app(ReportBlockCatalog::class),
        );
    }
}
