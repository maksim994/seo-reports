<?php

namespace App\Services;

use App\Models\ProjectIntegration;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexMetrikaDataService
{
    /** @return array{visits: float, users: float, bounce_rate: float, avg_duration: float}|null */
    public function fetchOverview(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
    ): ?array {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get('https://api-metrika.yandex.net/stat/v1/data', [
            'ids' => $counterId,
            'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate,ym:s:avgVisitDurationSeconds',
            'date1' => $dateFrom,
            'date2' => $dateTo,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Metrika API error: '.$response->body());
        }

        $totals = $response->json('totals');
        if (! is_array($totals) || count($totals) < 4) {
            return null;
        }

        return [
            'visits' => (float) $totals[0],
            'users' => (float) $totals[1],
            'bounce_rate' => (float) $totals[2],
            'avg_duration' => (float) $totals[3],
        ];
    }

    public function counterIdFromBinding(?ProjectIntegration $binding): ?string
    {
        if (! $binding?->external_resource_id) {
            return null;
        }

        return $binding->external_resource_id;
    }

    /** @return list<array{label: string, visits: float, users: float}> */
    public function fetchTrafficSources(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get('https://api-metrika.yandex.net/stat/v1/data', [
            'ids' => $counterId,
            'dimensions' => 'ym:s:lastTrafficSource',
            'metrics' => 'ym:s:visits,ym:s:users',
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'sort' => '-ym:s:visits',
            'limit' => $limit,
            'lang' => 'ru',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Metrika API error: '.$response->body());
        }

        return collect($response->json('data') ?? [])
            ->map(function (array $row) {
                $dims = $row['dimensions'] ?? [];
                $metrics = $row['metrics'] ?? [];

                return [
                    'label' => $this->translateTrafficSource(
                        isset($dims[0]['id']) ? (string) $dims[0]['id'] : null,
                        isset($dims[0]['name']) ? (string) $dims[0]['name'] : null,
                    ),
                    'visits' => (float) ($metrics[0] ?? 0),
                    'users' => (float) ($metrics[1] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function translateTrafficSource(?string $id, ?string $name): string
    {
        $byId = [
            'organic' => 'Поисковые системы',
            'direct' => 'Прямые заходы',
            'ad' => 'Рекламный трафик',
            'referral' => 'Переходы по ссылкам',
            'recommendation' => 'Рекомендательные системы',
            'recommend' => 'Рекомендательные системы',
            'internal' => 'Внутренние переходы',
            'social' => 'Социальные сети',
            'messenger' => 'Мессенджеры',
            'email' => 'Email',
            'saved' => 'Сохранённые страницы',
            'qrcode' => 'QR-код',
            'messaging' => 'Мессенджеры',
        ];

        if ($id && isset($byId[$id])) {
            return $byId[$id];
        }

        $byName = [
            'Search engine traffic' => 'Поисковые системы',
            'Direct traffic' => 'Прямые заходы',
            'Ad traffic' => 'Рекламный трафик',
            'Link traffic' => 'Переходы по ссылкам',
            'Recommendation system traffic' => 'Рекомендательные системы',
            'Internal traffic' => 'Внутренние переходы',
            'Social network traffic' => 'Социальные сети',
            'Messenger traffic' => 'Мессенджеры',
            'Email traffic' => 'Email',
            'Cached page traffic' => 'Кешированные страницы',
            'QR code traffic' => 'QR-код',
        ];

        if ($name && isset($byName[$name])) {
            return $byName[$name];
        }

        return $name ?: ($id ?: '—');
    }

    /** @return list<array{label: string, reaches: float, conversion: float}> */
    public function fetchGoals(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        $goals = $this->listActiveGoals($accessToken, $counterId);
        if ($goals === []) {
            return [];
        }

        $rows = [];
        foreach (array_chunk($goals, 10) as $chunk) {
            $metrics = [];
            foreach ($chunk as $goal) {
                $metrics[] = "ym:s:goal{$goal['id']}reaches";
                $metrics[] = "ym:s:goal{$goal['id']}conversionRate";
            }

            $response = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
            ])->get('https://api-metrika.yandex.net/stat/v1/data', [
                'ids' => $counterId,
                'metrics' => implode(',', $metrics),
                'date1' => $dateFrom,
                'date2' => $dateTo,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('Metrika API error: '.$response->body());
            }

            $totals = $response->json('totals') ?? [];
            foreach ($chunk as $index => $goal) {
                $rows[] = [
                    'label' => $goal['name'],
                    'reaches' => (float) ($totals[$index * 2] ?? 0),
                    'conversion' => (float) ($totals[$index * 2 + 1] ?? 0),
                ];
            }
        }

        return collect($rows)
            ->sortByDesc('reaches')
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function listActiveGoals(string $accessToken, string $counterId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get("https://api-metrika.yandex.net/management/v1/counter/{$counterId}/goals");

        if (! $response->successful()) {
            throw new RuntimeException('Metrika goals API error: '.$response->body());
        }

        return collect($response->json('goals') ?? [])
            ->filter(fn (array $goal) => ($goal['status'] ?? '') === 'Active')
            ->map(fn (array $goal) => [
                'id' => (int) $goal['id'],
                'name' => (string) ($goal['name'] ?? $goal['id']),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, visits: float, users: float}> */
    public function fetchDevices(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:deviceCategory',
                'metrics' => 'ym:s:visits,ym:s:users',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => '-ym:s:visits',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $this->translateDevice($id, $name),
            ['visits', 'users'],
        );
    }

    /** @return list<array{label: string, visits: float, users: float}> */
    public function fetchGeo(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 15,
    ): array {
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:regionCountry',
                'metrics' => 'ym:s:visits,ym:s:users',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => '-ym:s:visits',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $name ?: ($id ?: '—'),
            ['visits', 'users'],
        );
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchDailyVisits(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
    ): array {
        return $this->mapTimeSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:date',
                'metrics' => 'ym:s:visits',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => 'ym:s:date',
                'limit' => 10000,
            ]),
        );
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchMonthlyVisits(
        string $accessToken,
        string $counterId,
        string $periodEnd,
        int $months = 12,
    ): array {
        $end = \Carbon\Carbon::parse($periodEnd)->startOfMonth();
        $start = $end->copy()->subMonths($months - 1);

        return $this->mapTimeSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:month',
                'metrics' => 'ym:s:visits',
                'date1' => $start->format('Y-m-d'),
                'date2' => $end->copy()->endOfMonth()->format('Y-m-d'),
                'sort' => 'ym:s:month',
                'limit' => $months,
            ]),
            fn (string $label) => $this->formatMonthLabel($label),
        );
    }

    /** @return list<array{label: string, visits: float, users: float}> */
    public function fetchSearchEngines(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:searchEngine',
                'metrics' => 'ym:s:visits,ym:s:users',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'filters' => "ym:s:lastTrafficSource=='organic'",
                'sort' => '-ym:s:visits',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $name ?: ($id ?: '—'),
            ['visits', 'users'],
        );
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchOrganicDaily(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
    ): array {
        return $this->mapTimeSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:date',
                'metrics' => 'ym:s:visits',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'filters' => "ym:s:lastTrafficSource=='organic'",
                'sort' => 'ym:s:date',
                'limit' => 10000,
            ]),
        );
    }

    /** @return list<array{label: string, visits: float, users: float, bounce_rate: float}> */
    public function fetchLandingPages(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 15,
    ): array {
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:startURL',
                'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => '-ym:s:visits',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $this->truncateUrl($name ?: $id),
            ['visits', 'users', 'bounce_rate'],
        );
    }

    /** @return list<array{label: string, visits: float, bounce_rate: float}> */
    public function fetchHighBouncePages(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
        int $minVisits = 10,
    ): array {
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:startURL',
                'metrics' => 'ym:s:visits,ym:s:bounceRate',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'filters' => "ym:s:visits>={$minVisits}",
                'sort' => '-ym:s:bounceRate',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $this->truncateUrl($name ?: $id),
            ['visits', 'bounce_rate'],
        );
    }

    /** @return list<array{label: string, conversions: float, visits: float, conversion_rate: float}> */
    public function fetchConversionsBySource(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        return collect($this->statRequest($accessToken, [
            'ids' => $counterId,
            'dimensions' => 'ym:s:lastTrafficSource',
            'metrics' => 'ym:s:sumGoalReachesAny,ym:s:visits,ym:s:goalConversionRateAny',
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'sort' => '-ym:s:sumGoalReachesAny',
            'limit' => $limit,
        ])['data'] ?? [])
            ->map(function (array $row) {
                $dims = $row['dimensions'] ?? [];
                $metrics = $row['metrics'] ?? [];

                return [
                    'label' => $this->translateTrafficSource(
                        isset($dims[0]['id']) ? (string) $dims[0]['id'] : null,
                        isset($dims[0]['name']) ? (string) $dims[0]['name'] : null,
                    ),
                    'conversions' => (float) ($metrics[0] ?? 0),
                    'visits' => (float) ($metrics[1] ?? 0),
                    'conversion_rate' => (float) ($metrics[2] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @param  list<string>  $metricKeys */
    private function mapDimensionRows(
        array $payload,
        callable $labelResolver,
        array $metricKeys,
    ): array {
        return collect($payload['data'] ?? [])
            ->map(function (array $row) use ($labelResolver, $metricKeys) {
                $dims = $row['dimensions'] ?? [];
                $metrics = $row['metrics'] ?? [];
                $mapped = [
                    'label' => $labelResolver(
                        isset($dims[0]['id']) ? (string) $dims[0]['id'] : null,
                        isset($dims[0]['name']) ? (string) $dims[0]['name'] : null,
                    ),
                ];

                foreach ($metricKeys as $index => $key) {
                    $mapped[$key] = (float) ($metrics[$index] ?? 0);
                }

                return $mapped;
            })
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: float}> */
    private function mapTimeSeries(array $payload, ?callable $labelFormatter = null): array
    {
        return collect($payload['data'] ?? [])
            ->map(function (array $row) use ($labelFormatter) {
                $dims = $row['dimensions'] ?? [];
                $metrics = $row['metrics'] ?? [];
                $rawLabel = (string) ($dims[0]['name'] ?? $dims[0]['id'] ?? '—');
                $label = $labelFormatter ? $labelFormatter($rawLabel) : $this->formatDayLabel($rawLabel);

                return [
                    'label' => $label,
                    'value' => (float) ($metrics[0] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function statRequest(string $accessToken, array $params): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get('https://api-metrika.yandex.net/stat/v1/data', array_merge([
            'lang' => 'ru',
        ], $params));

        if (! $response->successful()) {
            throw new RuntimeException('Metrika API error: '.$response->body());
        }

        return $response->json();
    }

    private function translateDevice(?string $id, ?string $name): string
    {
        $byId = [
            'desktop' => 'Компьютеры',
            'mobile' => 'Смартфоны',
            'tablet' => 'Планшеты',
            'tv' => 'Smart TV',
        ];

        if ($id && isset($byId[$id])) {
            return $byId[$id];
        }

        $byName = [
            'Desktop' => 'Компьютеры',
            'Mobile' => 'Смартфоны',
            'Tablet' => 'Планшеты',
            'Smart TV' => 'Smart TV',
        ];

        if ($name && isset($byName[$name])) {
            return $byName[$name];
        }

        return $name ?: ($id ?: '—');
    }

    private function formatDayLabel(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 8, 2).'.'.substr($value, 5, 2);
        }

        return $value;
    }

    private function formatMonthLabel(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            $months = [
                '01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр',
                '05' => 'Май', '06' => 'Июн', '07' => 'Июл', '08' => 'Авг',
                '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек',
            ];

            return ($months[$matches[2]] ?? $matches[2]).' '.$matches[1];
        }

        return $value;
    }

    private function truncateUrl(?string $url): string
    {
        $url = $url ?: '—';
        if (mb_strlen($url) <= 72) {
            return $url;
        }

        return mb_substr($url, 0, 71).'…';
    }
}
