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
use App\Services\ReportGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportBlockRenderersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{context: ReportRenderContext, registry: ReportBlockRegistry} */
    private function makeContext(User $user, Project $project, array $bindings = []): array
    {
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'Tpl']);
        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'compare_period_start' => '2026-03-01',
            'compare_period_end' => '2026-03-31',
        ]);

        $bindingsByProvider = collect($bindings)->keyBy(fn ($b) => $b->integration->provider->value);

        $context = new ReportRenderContext(
            $project,
            $template,
            $job,
            $bindingsByProvider,
            app(ReportBlockCatalog::class),
        );

        return [
            'context' => $context,
            'registry' => app(ReportBlockRegistry::class),
        ];
    }

    private function bindIntegration(User $user, Project $project, IntegrationProvider $provider, string $resourceId): \App\Models\ProjectIntegration
    {
        $integration = Integration::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'credentials' => ['access_token' => $provider->value.'-token'],
            'status' => 'active',
        ]);

        return $project->projectIntegrations()->create([
            'integration_id' => $integration->id,
            'external_resource_id' => $resourceId,
            'external_resource_label' => 'Test resource',
        ])->load('integration');
    }

    public function test_metrika_traffic_sources_block_renders_table(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['name' => 'Поисковые системы']],
                        'metrics' => [500, 400],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_traffic_sources', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Поисковые системы', $result->html);
        $this->assertStringContainsString('500', $result->html);
    }

    public function test_metrika_goals_block_renders_table(): void
    {
        Http::fake([
            'api-metrika.yandex.net/management/v1/counter/*/goals' => Http::response([
                'goals' => [
                    ['id' => 1, 'name' => 'Заявка', 'status' => 'Active'],
                ],
            ]),
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'totals' => [42, 3.5],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_goals', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Заявка', $result->html);
    }

    public function test_metrika_search_engines_timeline_uses_configured_25_month_period(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [
                            ['name' => '2026-04'],
                            ['name' => 'Yandex'],
                        ],
                        'metrics' => [120],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_search_engines_timeline', $context, [
            'chart_period' => '25_months',
        ]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Yandex', $result->html);

        Http::assertSent(fn ($request) => $request['date1'] === '2024-04-01'
            && $request['date2'] === '2026-04-30'
            && $request['dimensions'] === 'ym:s:month,ym:s:searchEngine'
            && $request['filters'] === "ym:s:lastTrafficSource=='organic'");
    }

    public function test_metrika_page_groups_block_renders_grouped_timeline(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => function ($request) {
                $visits = $request['date2'] === '2026-04-30' ? 120 : 200;

                return Http::response([
                    'data' => [
                        [
                            'dimensions' => [
                                ['id' => '2026-04-01', 'name' => '4'],
                                ['name' => 'https://example.com/blog/post'],
                            ],
                            'metrics' => [$visits],
                        ],
                    ],
                ]);
            },
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'settings' => [
                'page_groups' => [
                    ['id' => 'info', 'label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
                ],
            ],
        ]);
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_page_groups', $context, [
            'chart_period' => '12_months',
            'traffic_scope' => 'organic',
        ]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Инфо раздел', $result->html);
        $this->assertStringContainsString('Органика по типам страниц', $result->html);
        $this->assertStringContainsString('delta-down', $result->html);
        $this->assertStringContainsString('-40.0 %', $result->html);
        Http::assertSent(fn ($request) => $request['dimensions'] === 'ym:s:month,ym:s:startURL'
            && $request['filters'] === "ym:s:lastTrafficSource=='organic'");
    }

    public function test_metrika_page_group_conversions_block_renders_grouped_goals_timeline(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => function ($request) {
                $conversions = $request['date2'] === '2026-04-30' ? 12 : 20;

                return Http::response([
                    'data' => [
                        [
                            'dimensions' => [
                                ['id' => '2026-04-01', 'name' => '4'],
                                ['name' => 'https://example.com/blog/post'],
                            ],
                            'metrics' => [$conversions, 120],
                        ],
                    ],
                ]);
            },
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'settings' => [
                'page_groups' => [
                    ['id' => 'info', 'label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
                ],
            ],
        ]);
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_page_group_conversions', $context, [
            'chart_period' => '12_months',
            'traffic_scope' => 'organic',
        ]);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Инфо раздел', $result->html);
        $this->assertStringContainsString('Конверсии из органики по типам страниц', $result->html);
        $this->assertStringContainsString('delta-down', $result->html);
        $this->assertStringContainsString('-40.0 %', $result->html);
        Http::assertSent(fn ($request) => $request['dimensions'] === 'ym:s:month,ym:s:startURL'
            && $request['metrics'] === 'ym:s:sumGoalReachesAny,ym:s:visits'
            && $request['filters'] === "ym:s:lastTrafficSource=='organic'");
    }

    public function test_ga_overview_block_renders_metrics(): void
    {
        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::response([
                'rows' => [
                    ['metricValues' => [
                        ['value' => '1200'],
                        ['value' => '900'],
                        ['value' => '0.65'],
                    ]],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::GoogleAnalytics, 'properties/999');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('ga_overview', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('1 200', $result->html);
    }

    public function test_ga_channels_block_renders_table(): void
    {
        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::response([
                'rows' => [
                    [
                        'dimensionValues' => [['value' => 'Organic Search']],
                        'metricValues' => [['value' => '300'], ['value' => '250']],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::GoogleAnalytics, '999');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('ga_channels', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Organic Search', $result->html);
    }

    public function test_gsc_top_queries_block_renders_table(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'rows' => [
                    [
                        'keys' => ['seo отчёт'],
                        'clicks' => 10,
                        'impressions' => 100,
                        'ctr' => 0.1,
                        'position' => 5.2,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::GoogleSearchConsole, 'https://example.com/');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('gsc_top_queries', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('seo отчёт', $result->html);
    }

    public function test_webmaster_queries_block_renders_table(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 1]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/search-queries/popular*' => Http::response([
                'queries' => [
                    [
                        'query_text' => 'купить диван',
                        'indicators' => [
                            'TOTAL_SHOWS' => 1000,
                            'TOTAL_CLICKS' => 50,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexWebmaster, 'host:example.com:443');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('webmaster_queries', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('купить диван', $result->html);
    }

    public function test_metrika_devices_block_renders_table(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['id' => 'desktop', 'name' => 'Desktop']],
                        'metrics' => [300, 250],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::YandexMetrika, '12345');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('metrika_devices', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Компьютеры', $result->html);
    }

    public function test_gsc_performance_block_renders_summary(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'rows' => [
                    [
                        'clicks' => 120,
                        'impressions' => 5000,
                        'ctr' => 0.024,
                        'position' => 8.4,
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $binding = $this->bindIntegration($user, $project, IntegrationProvider::GoogleSearchConsole, 'https://example.com/');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$binding]);

        $result = $registry->render('gsc_performance', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('120', $result->html);
    }

    public function test_search_compare_block_renders_both_sources(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response([
                'rows' => [
                    ['clicks' => 80, 'impressions' => 2000, 'ctr' => 0.04, 'position' => 6.1],
                ],
            ]),
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 1]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/search-queries/all/history*' => Http::response([
                'indicators' => [
                    'TOTAL_SHOWS' => [['date' => '2026-04-01T00:00:00.000+03:00', 'value' => 1000]],
                    'TOTAL_CLICKS' => [['date' => '2026-04-01T00:00:00.000+03:00', 'value' => 40]],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $gsc = $this->bindIntegration($user, $project, IntegrationProvider::GoogleSearchConsole, 'https://example.com/');
        $wm = $this->bindIntegration($user, $project, IntegrationProvider::YandexWebmaster, 'host:example.com:443');
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project, [$gsc, $wm]);

        $result = $registry->render('search_clicks_compare', $context, null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Яндекс', $result->html);
        $this->assertStringContainsString('Google', $result->html);
    }

    public function test_integration_block_without_binding_shows_unavailable(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        ['context' => $context, 'registry' => $registry] = $this->makeContext($user, $project);

        $result = $registry->render('ga_overview', $context, null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('не привязан', $result->html);
    }

    public function test_full_report_with_all_analytics_blocks(): void
    {
        Storage::fake('local');
        config(['reports.storage_disk' => 'local']);

        Http::fake([
            'api-metrika.yandex.net/management/v1/counter/*/goals' => Http::response(['goals' => []]),
            'api-metrika.yandex.net/stat/v1/data*' => Http::response(['totals' => [100, 80, 20, 60], 'data' => []]),
            'analyticsdata.googleapis.com/*' => Http::response(['rows' => []]),
            'www.googleapis.com/webmasters/v3/sites/*' => Http::response(['rows' => []]),
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 1]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/search-queries/popular*' => Http::response(['queries' => []]),
        ]);

        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Full Report']);
        $template = ReportTemplate::create(['user_id' => $user->id, 'name' => 'All blocks']);
        foreach ([
            'title_page',
            'metrika_overview',
            'metrika_traffic_sources',
            'metrika_goals',
            'ga_overview',
            'ga_channels',
            'gsc_top_queries',
            'webmaster_queries',
        ] as $i => $type) {
            $template->blocks()->create(['block_type' => $type, 'sort_order' => $i]);
        }

        foreach (IntegrationProvider::cases() as $provider) {
            $this->bindIntegration($user, $project, $provider, match ($provider) {
                IntegrationProvider::GoogleAnalytics => 'properties/1',
                IntegrationProvider::GoogleSearchConsole => 'https://example.com/',
                default => 'resource-1',
            });
        }

        $job = \App\Models\ReportJob::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        app(ReportGeneratorService::class)->generate($job);

        $job->refresh();
        $this->assertSame(ReportJobStatus::Done, $job->status);
        $this->assertCount(2, $job->files);
    }
}
