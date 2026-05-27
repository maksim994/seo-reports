<?php

namespace Tests\Unit;

use App\Integrations\YandexMetrikaProvider;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexMetrikaProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_resources_includes_counter_id_and_site_from_site2(): void
    {
        Http::fake([
            'api-metrika.yandex.net/*' => Http::response([
                'counters' => [
                    [
                        'id' => 12345,
                        'name' => 'Bitrix marketplace',
                        'site2' => ['site' => 'bitrix-developers.ru', 'domain' => 'bitrix-developers.ru'],
                    ],
                    [
                        'id' => 67890,
                        'name' => '',
                        'site2' => ['site' => '37otdelka.ru'],
                    ],
                    [
                        'id' => 11111,
                        'name' => 'format',
                        'site' => '',
                        'site2' => ['site' => ''],
                    ],
                ],
            ]),
        ]);

        $integration = Integration::make([
            'credentials' => ['access_token' => 'token'],
        ]);

        $resources = (new YandexMetrikaProvider)->listResources($integration);

        $this->assertSame('#12345 — Bitrix marketplace — bitrix-developers.ru', $resources[0]['label']);
        $this->assertSame('bitrix-developers.ru', $resources[0]['meta']['site']);
        $this->assertSame(12345, $resources[0]['meta']['counter_id']);

        $this->assertSame('#67890 — 37otdelka.ru', $resources[1]['label']);

        $this->assertSame('#11111 — format', $resources[2]['label']);
        $this->assertNull($resources[2]['meta']['site']);
    }
}
