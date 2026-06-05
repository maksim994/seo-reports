<?php

namespace Tests\Unit;

use App\Services\YandexMetrikaDataService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMetrikaDataServiceTest extends TestCase
{
    public function test_traffic_sources_are_translated_to_russian(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['id' => 'organic', 'name' => 'Search engine traffic']],
                        'metrics' => [100, 80],
                    ],
                    [
                        'dimensions' => [['id' => 'direct', 'name' => 'Direct traffic']],
                        'metrics' => [50, 40],
                    ],
                ],
            ]),
        ]);

        $rows = app(YandexMetrikaDataService::class)->fetchTrafficSources(
            'token',
            '123',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertSame('Поисковые системы', $rows[0]['label']);
        $this->assertSame('Прямые заходы', $rows[1]['label']);
    }

    public function test_devices_are_translated_to_russian(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['id' => 'mobile', 'name' => 'Mobile']],
                        'metrics' => [200, 150],
                    ],
                ],
            ]),
        ]);

        $rows = app(YandexMetrikaDataService::class)->fetchDevices(
            'token',
            '123',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertSame('Смартфоны', $rows[0]['label']);
        $this->assertSame(200.0, $rows[0]['visits']);
    }

    public function test_daily_visits_returns_time_series(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['name' => '2026-04-01']],
                        'metrics' => [100],
                    ],
                    [
                        'dimensions' => [['name' => '2026-04-02']],
                        'metrics' => [120],
                    ],
                ],
            ]),
        ]);

        $rows = app(YandexMetrikaDataService::class)->fetchDailyVisits(
            'token',
            '123',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertCount(2, $rows);
        $this->assertSame('01.04', $rows[0]['label']);
        $this->assertSame(100.0, $rows[0]['value']);
    }

    public function test_monthly_series_prefers_full_date_id_over_short_month_name(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [
                            ['id' => '2026-03-01', 'name' => '3'],
                            ['id' => 'organic', 'name' => 'Search engine traffic'],
                        ],
                        'metrics' => [1228],
                    ],
                ],
            ]),
        ]);

        $series = app(YandexMetrikaDataService::class)->fetchMonthlyVisitsByTrafficSourceRange(
            'token',
            '123',
            '2026-01-01',
            '2026-12-31',
        );

        $this->assertSame(['Мар 2026'], $series['categories']);
        $this->assertSame('Поисковые системы', $series['series'][0]['name']);
        $this->assertSame([1228.0], $series['series'][0]['data']);
    }

    public function test_page_groups_monthly_timeline_groups_start_urls_by_regex(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [
                            ['id' => '2026-03-01', 'name' => '3'],
                            ['name' => 'https://example.com/blog/post-1'],
                        ],
                        'metrics' => [100],
                    ],
                    [
                        'dimensions' => [
                            ['id' => '2026-03-01', 'name' => '3'],
                            ['name' => 'https://example.com/catalog/item'],
                        ],
                        'metrics' => [250],
                    ],
                    [
                        'dimensions' => [
                            ['id' => '2026-04-01', 'name' => '4'],
                            ['name' => 'https://example.com/blog/post-2'],
                        ],
                        'metrics' => [120],
                    ],
                ],
            ]),
        ]);

        $series = app(YandexMetrikaDataService::class)->fetchPageGroupsMonthlyTimelineRange(
            'token',
            '123',
            '2026-03-01',
            '2026-04-30',
            [
                ['id' => 'info', 'label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
                ['id' => 'catalog', 'label' => 'Каталог', 'pattern' => '^/catalog/', 'enabled' => true],
            ],
            'organic',
        );

        $this->assertSame(['Мар 2026', 'Апр 2026'], $series['categories']);
        $this->assertSame('Каталог', $series['series'][0]['name']);
        $this->assertSame([250.0, 0.0], $series['series'][0]['data']);
        $this->assertSame('Инфо раздел', $series['series'][1]['name']);
        $this->assertSame([100.0, 120.0], $series['series'][1]['data']);
        $this->assertSame(250.0, $series['rows'][0]['visits']);

        Http::assertSent(fn ($request) => $request['filters'] === "ym:s:lastTrafficSource=='organic'");
    }

    public function test_page_group_conversions_monthly_timeline_groups_goal_reaches_by_regex(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [
                            ['id' => '2026-03-01', 'name' => '3'],
                            ['name' => 'https://example.com/blog/post-1'],
                        ],
                        'metrics' => [3, 2, 100],
                    ],
                    [
                        'dimensions' => [
                            ['id' => '2026-03-01', 'name' => '3'],
                            ['name' => 'https://example.com/catalog/item'],
                        ],
                        'metrics' => [10, 5, 250],
                    ],
                    [
                        'dimensions' => [
                            ['id' => '2026-04-01', 'name' => '4'],
                            ['name' => 'https://example.com/blog/post-2'],
                        ],
                        'metrics' => [4, 1, 120],
                    ],
                ],
            ]),
        ]);

        $series = app(YandexMetrikaDataService::class)->fetchPageGroupConversionsMonthlyTimelineRange(
            'token',
            '123',
            '2026-03-01',
            '2026-04-30',
            [
                ['id' => 'info', 'label' => 'Инфо раздел', 'pattern' => '^/blog/', 'enabled' => true],
                ['id' => 'catalog', 'label' => 'Каталог', 'pattern' => '^/catalog/', 'enabled' => true],
            ],
            [11, 22],
            'organic',
        );

        $this->assertSame(['Мар 2026', 'Апр 2026'], $series['categories']);
        $this->assertSame('Каталог', $series['series'][0]['name']);
        $this->assertSame([15.0, 0.0], $series['series'][0]['data']);
        $this->assertSame('Инфо раздел', $series['series'][1]['name']);
        $this->assertSame([5.0, 5.0], $series['series'][1]['data']);
        $this->assertSame(15.0, $series['rows'][0]['conversions']);
        $this->assertSame(6.0, $series['rows'][0]['conversion_rate']);

        Http::assertSent(fn ($request) => $request['metrics'] === 'ym:s:goal11reaches,ym:s:goal22reaches,ym:s:visits'
            && $request['filters'] === "ym:s:lastTrafficSource=='organic'");
    }

    public function test_conversions_by_source_calculates_conversion_rate(): void
    {
        Http::fake([
            'api-metrika.yandex.net/stat/v1/data*' => Http::response([
                'data' => [
                    [
                        'dimensions' => [['id' => 'organic', 'name' => 'Search engine traffic']],
                        'metrics' => [25, 100],
                    ],
                    [
                        'dimensions' => [['id' => 'direct', 'name' => 'Direct traffic']],
                        'metrics' => [5, 200],
                    ],
                ],
            ]),
        ]);

        $rows = app(YandexMetrikaDataService::class)->fetchConversionsBySource(
            'token',
            '123',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertSame('Поисковые системы', $rows[0]['label']);
        $this->assertSame(25.0, $rows[0]['conversions']);
        $this->assertSame(100.0, $rows[0]['visits']);
        $this->assertSame(25.0, $rows[0]['conversion_rate']);
        $this->assertSame(2.5, $rows[1]['conversion_rate']);
    }
}
