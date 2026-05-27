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
}
