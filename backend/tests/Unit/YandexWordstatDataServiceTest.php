<?php

namespace Tests\Unit;

use App\Services\YandexWordstatDataService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexWordstatDataServiceTest extends TestCase
{
    public function test_fetch_dynamics_series_maps_response(): void
    {
        Http::fake([
            'api.wordstat.yandex.net/v1/dynamics' => Http::response([
                'dynamics' => [
                    ['date' => '2025-01-01', 'count' => 12000, 'share' => 0.5],
                    ['date' => '2025-02-01', 'count' => 15000, 'share' => 0.55],
                ],
            ]),
        ]);

        $series = app(YandexWordstatDataService::class)->fetchDynamicsSeries(
            'token',
            'осевой вентилятор',
            'monthly',
            Carbon::parse('2025-02-28'),
            2,
        );

        $this->assertCount(2, $series);
        $this->assertSame(12000.0, $series[0]['value']);
        $this->assertSame(15000.0, $series[1]['value']);
    }

    public function test_fetch_top_requests_maps_response(): void
    {
        Http::fake([
            'api.wordstat.yandex.net/v1/topRequests' => Http::response([
                'topRequests' => [
                    ['phrase' => 'осевой вентилятор купить', 'count' => 5000],
                    ['phrase' => 'осевой вентилятор цена', 'count' => 3200],
                ],
            ]),
        ]);

        $rows = app(YandexWordstatDataService::class)->fetchTopRequests('token', 'осевой вентилятор');

        $this->assertSame('осевой вентилятор купить', $rows[0]['label']);
        $this->assertSame(5000.0, $rows[0]['value']);
    }

    public function test_build_dynamics_range_for_monthly_period(): void
    {
        [$from, $to] = app(YandexWordstatDataService::class)->buildDynamicsRange(
            'monthly',
            24,
            Carbon::parse('2026-04-25'),
        );

        $this->assertSame('2024-05-01', $from);
        $this->assertSame('2026-04-30', $to);
    }
}
