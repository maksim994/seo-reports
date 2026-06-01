<?php

namespace Tests\Unit;

use App\Services\KeysSoDataService;
use App\Support\KeysSoRateLimiter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KeysSoDataServiceTest extends TestCase
{
    public function test_list_monitoring_projects_paginates(): void
    {
        Http::fake([
            'api.keys.so/monitoring*' => Http::sequence()
                ->push([
                    'current_page' => 1,
                    'last_page' => 2,
                    'total' => 2,
                    'data' => [
                        ['id' => 1, 'name' => 'Project A', 'trackingItem' => 'a.ru', 'search_settings' => []],
                    ],
                ])
                ->push([
                    'current_page' => 2,
                    'last_page' => 2,
                    'total' => 2,
                    'data' => [
                        ['id' => 2, 'name' => 'Project B', 'trackingItem' => 'b.ru', 'search_settings' => []],
                    ],
                ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $projects = $service->listMonitoringProjects('token');

        $this->assertCount(2, $projects);
        $this->assertSame(1, $projects[0]['id']);
        $this->assertSame(2, $projects[1]['id']);
    }

    public function test_list_project_resources_merges_dashboard_projects(): void
    {
        Http::fake([
            'api.keys.so/monitoring*' => Http::response([
                'current_page' => 1,
                'last_page' => 1,
                'total' => 1,
                'data' => [
                    ['id' => 3451, 'name' => 'Koldiz', 'trackingItem' => 'koldiz.ru', 'search_settings' => []],
                ],
            ]),
            'api.keys.so/projects*' => Http::response([
                ['projectId' => 100, 'title' => 'Other', 'domain' => ['name' => 'other.ru']],
                ['projectId' => 200, 'title' => 'Koldiz dash', 'domain' => ['name' => 'koldiz.ru']],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $resources = $service->listProjectResources('token');

        $this->assertCount(2, $resources);
        $this->assertSame('3451', $resources[0]['id']);
        $this->assertSame('dashboard:100', $resources[1]['id']);
    }

    public function test_list_project_resources_enriches_missing_tracking_item_from_entity(): void
    {
        Http::fake([
            'api.keys.so/monitoring/3451/entity' => Http::response([
                'id' => 3451,
                'name' => 'Koldiz',
                'trackingItem' => 'koldiz.ru',
                'searchSettings' => [
                    ['engine' => 4, 'regionId' => 20950, 'regionName' => 'Москва'],
                ],
            ]),
            'api.keys.so/monitoring*' => Http::response([
                'current_page' => 1,
                'last_page' => 1,
                'total' => 1,
                'data' => [
                    [
                        'id' => 3451,
                        'name' => 'Koldiz',
                        'search_settings' => [
                            [
                                'search_engine' => 4,
                                'region_id' => 20950,
                                'region_name' => 'Москва',
                                'engine_name' => 'Google (десктоп)',
                            ],
                        ],
                    ],
                ],
            ]),
            'api.keys.so/projects*' => Http::response([]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $resources = $service->listProjectResources('token');

        $this->assertCount(1, $resources);
        $this->assertSame('3451', $resources[0]['id']);
        $this->assertSame('Koldiz · koldiz.ru (#3451)', $resources[0]['label']);
        $this->assertSame('koldiz.ru', $resources[0]['meta']['site']);
    }

    public function test_fetch_summary_maps_chart_data(): void
    {
        Http::fake([
            'api.keys.so/monitoring/146/report-chart*' => Http::response([
                'data' => [
                    '2026-04-01' => [
                        'it3_organic' => 2,
                        'it10_organic' => 5,
                        'it50_organic' => 8,
                        'it100_organic' => 10,
                        'total_words' => 20,
                        'day_avg_organic_pos' => 15.5,
                    ],
                    '2026-04-30' => [
                        'it3_organic' => 3,
                        'it10_organic' => 8,
                        'it50_organic' => 12,
                        'it100_organic' => 15,
                        'total_words' => 20,
                        'day_avg_organic_pos' => 12.4,
                    ],
                ],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $summary = $service->fetchSummary('token', 146, ['regionId' => 38, 'engine' => 0], '2026-04-01', '2026-04-30');

        $this->assertSame(40.0, $summary['visibility']);
        $this->assertSame(15.0, $summary['visibility_dynamic']);
        $this->assertSame(8, $summary['tops']['top10']);
        $this->assertSame(12.4, $summary['avg']);
    }

    public function test_fetch_positions_table_extracts_tracked_domain(): void
    {
        Http::fake([
            'api.keys.so/monitoring/146/report*' => Http::response([
                'data' => [
                    'words' => [
                        'кейсо' => [
                            'word' => 'кейсо',
                            'domains' => [
                                'keys.so' => [
                                    'is_competitor' => 0,
                                    'engines' => [[
                                        'regions' => [[
                                            'dates' => [
                                                ['organic_pos' => 8],
                                                ['organic_pos' => 10],
                                            ],
                                        ]],
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $rows = $service->fetchPositionsTable(
            'token',
            146,
            ['regionId' => 38, 'engine' => 0],
            '2026-04-01',
            '2026-04-30',
            50,
            'keys.so',
        );

        $this->assertCount(1, $rows);
        $this->assertSame('кейсо', $rows[0]['keyword']);
        $this->assertSame(8.0, $rows[0]['position']);
        $this->assertSame(10.0, $rows[0]['previous']);
        $this->assertSame(2.0, $rows[0]['delta']);
    }

    public function test_domain_from_project_strips_protocol_and_www(): void
    {
        $service = new KeysSoDataService(new KeysSoRateLimiter);

        $this->assertSame('example.ru', $service->domainFromProject('https://www.example.ru/'));
        $this->assertNull($service->domainFromProject(''));
    }

    public function test_find_monitoring_project_by_domain(): void
    {
        Http::fake([
            'api.keys.so/monitoring*' => Http::response([
                'current_page' => 1,
                'last_page' => 1,
                'data' => [
                    ['id' => 10, 'name' => 'Site', 'trackingItem' => 'www.demo.ru', 'search_settings' => []],
                ],
            ]),
            'api.keys.so/monitoring/10/entity' => Http::response([
                'id' => 10,
                'name' => 'Site',
                'trackingItem' => 'demo.ru',
                'searchSettings' => [['engine' => 0, 'regionId' => 38]],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $project = $service->findMonitoringProjectByDomain('token', 'https://demo.ru');

        $this->assertNotNull($project);
        $this->assertSame(10, $project['id']);
        $this->assertSame('demo.ru', $service->domainFromProject($project['tracking_item']));
    }

    public function test_fetch_site_queries_dashboard_maps_tops_and_keywords(): void
    {
        Http::fake([
            'api.keys.so/report/simple/domain_dashboard*' => Http::response([
                'it1' => 109,
                'it3' => 322,
                'it5' => 504,
                'it10' => 1023,
                'it50' => 4561,
                'aiAnswersCnt' => 301,
                'keys' => [
                    ['word' => 'запрос', 'pos' => 3, 'wsk' => 120],
                ],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $summary = $service->fetchSiteQueriesDashboard('token', 'demo.ru', 'msk', 5);

        $this->assertSame(109, $summary['top1']);
        $this->assertSame(301, $summary['ai_mentions']);
        $this->assertSame('запрос', $summary['keywords'][0]['keyword']);
    }

    public function test_fetch_links_dashboard_uses_report_totals(): void
    {
        Http::fake([
            'api.keys.so/report/simple/domain_dashboard*' => Http::response(['dr' => 33]),
            'api.keys.so/report/simple/links/backlinks-anchor*' => Http::response(['total' => 611, 'data' => []]),
            'api.keys.so/report/simple/links/backlinks-ip*' => Http::response(['total' => 1229, 'data' => []]),
            'api.keys.so/report/simple/links/backlinks-domains*' => Http::response(['total' => 594, 'data' => []]),
            'api.keys.so/report/simple/links/outlinks-domains*' => Http::response(['total' => 83, 'data' => []]),
            'api.keys.so/report/simple/links/outlinks?*' => Http::response(['total' => 7275, 'data' => []]),
            'api.keys.so/report/simple/links/backlinks?*' => Http::response(['total' => 3640, 'data' => []]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $summary = $service->fetchLinksDashboard('token', 'demo.ru');

        $this->assertSame(3640, $summary['incoming']);
        $this->assertSame(7275, $summary['outgoing']);
        $this->assertSame(33, $summary['dr']);
        $this->assertSame(611, $summary['anchors']);
    }

    public function test_fetch_dashboard_ai_mentions_maps_query_and_answer(): void
    {
        Http::fake([
            'api.keys.so/report/simple/ai-answers/state*' => Http::response(['ai_state' => 10]),
            'api.keys.so/report/simple/organic/ai-answers*' => Http::response([
                'data' => [
                    [
                        'word' => 'методы очистки',
                        'ai_answer' => '<strong>ЛОС</strong> — локальные очистные сооружения.<br>Некоторые методы очистки сточных вод...',
                        'created_at' => '2024-12-15',
                    ],
                ],
            ]),
        ]);

        $service = new KeysSoDataService(new KeysSoRateLimiter);
        $rows = $service->fetchDashboardAiMentions('token', 'demo.ru', 5);

        $this->assertCount(1, $rows);
        $this->assertSame('методы очистки', $rows[0]['query']);
        $this->assertSame('15.12.2024', $rows[0]['date']);
        $this->assertStringContainsString('ЛОС', $rows[0]['answer']);
        $this->assertStringContainsString('очистки сточных', $rows[0]['answer']);
        $this->assertStringNotContainsString('<strong>', $rows[0]['answer']);
    }
}
