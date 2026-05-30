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
}
