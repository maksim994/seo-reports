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
}
