<?php

namespace Tests\Unit;

use App\Services\TopvisorDataService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopvisorDataServiceTest extends TestCase
{
    public function test_list_projects_paginates_through_all_pages(): void
    {
        Http::fake([
            'api.topvisor.com/*' => Http::sequence()
                ->push([
                    'result' => [
                        ['id' => 1, 'name' => 'boostra.ru - ПФ', 'url' => 'boostra.ru', 'on' => 1, 'searchers' => []],
                    ],
                    'nextOffset' => 1,
                    'total' => 2,
                ])
                ->push([
                    'result' => [
                        ['id' => 2, 'name' => 'plastguard.ru', 'url' => 'plastguard.ru', 'on' => 1, 'searchers' => []],
                    ],
                    'nextOffset' => null,
                    'total' => 2,
                ]),
        ]);

        $projects = app(TopvisorDataService::class)->listProjects('67190', 'key');

        $this->assertCount(2, $projects);
        $this->assertSame('boostra.ru - ПФ', $projects[0]['name']);
        $this->assertSame('plastguard.ru', $projects[1]['name']);
    }

    public function test_project_resources_match_topvisor_project_list(): void
    {
        Http::fake([
            'api.topvisor.com/*' => function ($request) {
                $body = $request->data();
                $this->assertSame(['id', 'name', 'url', 'on'], $body['fields'] ?? null);

                return Http::response([
                    'result' => [
                        [
                            'id' => 28542294,
                            'name' => 'boostra.ru - ПФ',
                            'url' => 'boostra.ru',
                            'on' => 1,
                            'searchers' => [
                                [
                                    'name' => 'Yandex',
                                    'regions' => [
                                        ['index' => 1, 'areaName' => 'Москва и Московская область'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);
            },
        ]);

        $resources = app(TopvisorDataService::class)->listProjectResources('67190', 'key');

        $this->assertCount(1, $resources);
        $this->assertSame('28542294', $resources[0]['id']);
        $this->assertSame('boostra.ru - ПФ (#28542294)', $resources[0]['label']);
        $this->assertSame('boostra.ru - ПФ', $resources[0]['meta']['name']);
        $this->assertCount(1, $resources[0]['meta']['regions']);
    }

    public function test_inactive_projects_are_excluded_from_resources(): void
    {
        Http::fake([
            'api.topvisor.com/*' => Http::response([
                'result' => [
                    [
                        'id' => 100,
                        'name' => 'Active',
                        'url' => 'active.ru',
                        'on' => 0,
                        'searchers' => [
                            [
                                'name' => 'Yandex',
                                'regions' => [['index' => 1, 'areaName' => 'Москва']],
                            ],
                        ],
                    ],
                    ['id' => 200, 'name' => 'Archived', 'url' => 'archived.ru', 'on' => -1, 'searchers' => []],
                    ['id' => 300, 'name' => 'Empty', 'url' => 'empty.ru', 'on' => 0, 'searchers' => []],
                ],
            ]),
        ]);

        $resources = app(TopvisorDataService::class)->listProjectResources('1', 'key');

        $this->assertCount(1, $resources);
        $this->assertSame('100', $resources[0]['id']);
    }

    public function test_resolve_region_index_prefers_yandex(): void
    {
        Http::fake([
            'api.topvisor.com/*' => Http::response([
                'result' => [
                    [
                        'id' => 100,
                        'name' => 'demo.ru',
                        'url' => 'demo.ru',
                        'on' => 1,
                        'searchers' => [
                            [
                                'name' => 'Google',
                                'regions' => [['index' => 9, 'areaName' => 'Москва']],
                            ],
                            [
                                'name' => 'Yandex',
                                'regions' => [['index' => 3, 'areaName' => 'Москва']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $index = app(TopvisorDataService::class)->resolveRegionIndex('1', 'key', 100);

        $this->assertSame(3, $index);
    }

    public function test_fetch_top_distribution_parses_ranges_percent_and_delta(): void
    {
        Http::fake([
            'api.topvisor.com/v2/json/get/positions_2/summary*' => Http::response([
                'result' => [
                    'dates' => ['2025-06-01', '2026-06-01'],
                    'tops' => [
                        [
                            '1_3' => 940,
                            '1_10' => 2288,
                            '11_30' => 1398,
                            '31_50' => 467,
                            '51_100' => 76,
                            '101_10000' => 126,
                        ],
                        [
                            '1_3' => 1024,
                            '1_10' => 2506,
                            '11_30' => 1515,
                            '31_50' => 425,
                            '51_100' => 89,
                            '101_10000' => 73,
                        ],
                    ],
                ],
            ]),
            'api.topvisor.com/v2/json/get/keywords_2/keywords*' => Http::response([
                'total' => 4632,
            ]),
        ]);

        $data = app(TopvisorDataService::class)->fetchTopDistribution(
            '67190',
            'key',
            28542294,
            1,
            '2025-06-01',
            '2026-06-01',
        );

        $this->assertSame(['01.06.2025', '01.06.2026'], $data['check_dates']);
        $this->assertCount(6, $data['ranges']);

        $top3 = $data['ranges'][0];
        $this->assertSame('1–3', $top3['label']);
        $this->assertSame(1024, $top3['count']);
        $this->assertSame(84, $top3['delta']);
        $this->assertSame(22, $top3['percent']);

        $top10 = $data['ranges'][1];
        $this->assertSame(2506, $top10['count']);
        $this->assertSame(218, $top10['delta']);
        $this->assertSame(54, $top10['percent']);
    }

    public function test_fetch_summary_maps_latest_topvisor_tops(): void
    {
        Http::fake([
            'api.topvisor.com/v2/json/get/positions_2/summary*' => Http::response([
                'result' => [
                    'visibilities' => [120.5, 133.2],
                    'avgs' => [18.2, 17.1],
                    'visibility_dynamic' => 12.7,
                    'tops' => [
                        ['1_3' => 100, '1_10' => 200, '11_30' => 300, '51_100' => 10, '101_10000' => 5],
                        ['1_3' => 150, '1_10' => 250, '11_30' => 350, '51_100' => 20, '101_10000' => 8],
                    ],
                ],
            ]),
        ]);

        $summary = app(TopvisorDataService::class)->fetchSummary(
            '1',
            'key',
            100,
            1,
            '2025-01-01',
            '2025-02-01',
        );

        $this->assertSame(133.2, $summary['visibility']);
        $this->assertSame(17.1, $summary['avg']);
        $this->assertSame([
            'top3' => 150,
            'top10' => 250,
            'top30' => 350,
            'top100' => 28,
        ], $summary['tops']);
    }
}
