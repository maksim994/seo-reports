<?php

namespace Tests\Unit;

use App\Services\YandexWebmasterDataService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexWebmasterDataServiceTest extends TestCase
{
    public function test_search_summary_parses_indicators_format(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 42]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/search-queries/all/history*' => Http::response([
                'indicators' => [
                    'TOTAL_SHOWS' => [
                        ['date' => '2026-04-01T00:00:00.000+03:00', 'value' => 500],
                        ['date' => '2026-04-02T00:00:00.000+03:00', 'value' => 300],
                    ],
                    'TOTAL_CLICKS' => [
                        ['date' => '2026-04-01T00:00:00.000+03:00', 'value' => 25],
                        ['date' => '2026-04-02T00:00:00.000+03:00', 'value' => 15],
                    ],
                ],
            ]),
        ]);

        $summary = app(YandexWebmasterDataService::class)->fetchSearchSummary(
            'token',
            'https:www.example.com:443',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertNotNull($summary);
        $this->assertSame(800.0, $summary['shows']);
        $this->assertSame(40.0, $summary['clicks']);
        $this->assertSame(5.0, $summary['ctr']);
    }

    public function test_indexing_history_parses_http_2xx_indicator(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 7]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/indexing/history*' => Http::response([
                'indicators' => [
                    'HTTP_2XX' => [
                        ['date' => '2026-04-10T00:00:00.000+03:00', 'value' => 120],
                        ['date' => '2026-04-11T00:00:00.000+03:00', 'value' => 130],
                    ],
                ],
            ]),
        ]);

        $series = app(YandexWebmasterDataService::class)->fetchIndexingHistory(
            'token',
            'https:www.example.com:443',
            '2026-04-01',
            '2026-04-30',
        );

        $this->assertCount(2, $series);
        $this->assertSame(120.0, $series[0]['value']);
        $this->assertSame('10.04', $series[0]['label']);
    }

    public function test_sqi_history_parses_points(): void
    {
        Http::fake([
            'api.webmaster.yandex.net/v4/user' => Http::response(['user_id' => 3]),
            'api.webmaster.yandex.net/v4/user/*/hosts/*/sqi-history*' => Http::response([
                'points' => [
                    ['date' => '2026-03-15T00:00:00.000+03:00', 'value' => 450],
                ],
            ]),
        ]);

        $series = app(YandexWebmasterDataService::class)->fetchSqiHistory(
            'token',
            'https:www.example.com:443',
            '2026-03-01',
            '2026-03-31',
        );

        $this->assertCount(1, $series);
        $this->assertSame(450.0, $series[0]['value']);
    }
}
