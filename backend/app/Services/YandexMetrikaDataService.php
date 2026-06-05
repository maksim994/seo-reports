<?php

namespace App\Services;

use App\Support\ReportFetch;
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
        $payload = ReportFetch::remember(
            'metrika.overview.'.sha1($accessToken.'|'.$counterId.'|'.$dateFrom.'|'.$dateTo),
            function () use ($accessToken, $counterId, $dateFrom, $dateTo) {
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

                return $response->json();
            },
        );

        $totals = $payload['totals'] ?? null;
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
        return $this->mapDimensionRows(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:lastTrafficSource',
                'metrics' => 'ym:s:visits,ym:s:users',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => '-ym:s:visits',
                'limit' => $limit,
            ]),
            fn (?string $id, ?string $name) => $this->translateTrafficSource($id, $name),
            ['visits', 'users'],
        );
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

    /** @return list<array{id: int, name: string}> */
    public function listGoals(string $accessToken, string $counterId): array
    {
        return $this->listActiveGoals($accessToken, $counterId);
    }

    /** @return list<array{label: string, reaches: float, conversion: float}> */
    public function fetchGoals(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        ?array $goalIds = null,
        ?string $trafficSource = null,
        int $limit = 10,
    ): array {
        $goals = $this->listActiveGoals($accessToken, $counterId);
        if ($goals === []) {
            return [];
        }

        if ($goalIds !== null) {
            $goals = array_values(array_filter(
                $goals,
                fn (array $goal) => in_array($goal['id'], $goalIds, true),
            ));
        }

        if ($goals === []) {
            return [];
        }

        $queryParams = [
            'ids' => $counterId,
            'date1' => $dateFrom,
            'date2' => $dateTo,
        ];
        if ($trafficSource !== null) {
            $queryParams['filters'] = "ym:s:lastTrafficSource=='{$trafficSource}'";
        }

        $rows = [];
        foreach (array_chunk($goals, 10) as $chunk) {
            $metrics = [];
            foreach ($chunk as $goal) {
                $metrics[] = "ym:s:goal{$goal['id']}reaches";
                $metrics[] = "ym:s:goal{$goal['id']}conversionRate";
            }

            $params = array_merge($queryParams, ['metrics' => implode(',', $metrics)]);
            $payload = ReportFetch::remember(
                'metrika.goals.metrics.'.sha1($accessToken.'|'.$counterId.'|'.$dateFrom.'|'.$dateTo.'|'.json_encode($params)),
                function () use ($accessToken, $params) {
                    $response = Http::withHeaders([
                        'Authorization' => 'OAuth '.$accessToken,
                    ])->get('https://api-metrika.yandex.net/stat/v1/data', $params);

                    if (! $response->successful()) {
                        throw new RuntimeException('Metrika API error: '.$response->body());
                    }

                    return $response->json();
                },
            );

            $totals = $payload['totals'] ?? [];
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
        return ReportFetch::remember(
            'metrika.goals.list.'.sha1($accessToken.'|'.$counterId),
            function () use ($accessToken, $counterId) {
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
            },
        );
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

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    public function fetchVisitsTimeSeriesByTrafficSource(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        string $timeDimension = 'ym:s:date',
        int $maxSources = 8,
    ): array {
        $dateFormatter = $timeDimension === 'ym:s:month'
            ? fn (string $label) => $this->formatMonthLabel($label)
            : fn (string $label) => $this->formatDayLabel($label);

        return $this->pivotDateSourceSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => $timeDimension.',ym:s:lastTrafficSource',
                'metrics' => 'ym:s:visits',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => $timeDimension,
                'limit' => 10000,
            ]),
            $dateFormatter,
            $maxSources,
        );
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchMonthlyVisits(
        string $accessToken,
        string $counterId,
        string $periodEnd,
        int $months = 12,
    ): array {
        $range = $this->monthRange($periodEnd, $months);

        return $this->mapTimeSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:month',
                'metrics' => 'ym:s:visits',
                'date1' => $range['from'],
                'date2' => $range['to'],
                'sort' => 'ym:s:month',
                'limit' => $months,
            ]),
            fn (string $label) => $this->formatMonthLabel($label),
        );
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    public function fetchMonthlyVisitsByTrafficSource(
        string $accessToken,
        string $counterId,
        string $periodEnd,
        int $months = 12,
        int $maxSources = 8,
    ): array {
        $range = $this->monthRange($periodEnd, $months);

        return $this->fetchMonthlyVisitsByTrafficSourceRange($accessToken, $counterId, $range['from'], $range['to'], $maxSources);
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    public function fetchMonthlyVisitsByTrafficSourceRange(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $maxSources = 8,
    ): array {
        return $this->pivotDateSourceSeries(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:month,ym:s:lastTrafficSource',
                'metrics' => 'ym:s:visits',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => 'ym:s:month',
                'limit' => 10000,
            ]),
            fn (string $label) => $this->formatMonthLabel($label),
            $maxSources,
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

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    public function fetchSearchEnginesMonthlyTimeline(
        string $accessToken,
        string $counterId,
        string $periodEnd,
        int $months = 13,
        int $maxEngines = 8,
    ): array {
        $range = $this->monthRange($periodEnd, $months);

        return $this->fetchSearchEnginesMonthlyTimelineRange($accessToken, $counterId, $range['from'], $range['to'], $maxEngines);
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    public function fetchSearchEnginesMonthlyTimelineRange(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $maxEngines = 8,
    ): array {

        $matrix = [];
        $monthKeys = [];

        foreach ($this->statRequest($accessToken, [
            'ids' => $counterId,
            'dimensions' => 'ym:s:month,ym:s:searchEngine',
            'metrics' => 'ym:s:visits',
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'filters' => "ym:s:lastTrafficSource=='organic'",
            'sort' => 'ym:s:month',
            'limit' => 10000,
        ])['data'] ?? [] as $row) {
            $dims = $row['dimensions'] ?? [];
            $monthRaw = $this->dimensionDateRaw($dims[0] ?? []);
            $engine = (string) ($dims[1]['name'] ?? $dims[1]['id'] ?? '—');
            $visits = (float) ($row['metrics'][0] ?? 0);

            if ($monthRaw === '') {
                continue;
            }

            $monthKeys[$monthRaw] = true;
            $matrix[$engine][$monthRaw] = ($matrix[$engine][$monthRaw] ?? 0) + $visits;
        }

        $sortedMonths = array_keys($monthKeys);
        sort($sortedMonths);
        $categories = array_map(fn (string $m) => $this->formatMonthLabel($m), $sortedMonths);

        $totals = [];
        foreach ($matrix as $engine => $byMonth) {
            $totals[$engine] = array_sum($byMonth);
        }
        arsort($totals);
        $engines = array_slice(array_keys($totals), 0, $maxEngines);

        $series = [];
        foreach ($engines as $engine) {
            $data = [];
            foreach ($sortedMonths as $monthRaw) {
                $data[] = round((float) ($matrix[$engine][$monthRaw] ?? 0), 2);
            }
            $series[] = ['name' => $engine, 'data' => $data];
        }

        return ['categories' => $categories, 'series' => $series];
    }

    /** @return list<array{label: string, conversions: float, visits: float, conversion_rate: float}> */
    public function fetchConversionsBySource(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        ?array $goalIds = null,
        int $limit = 10,
    ): array {
        $metrics = $this->conversionMetrics($goalIds);

        return collect($this->statRequest($accessToken, [
            'ids' => $counterId,
            'dimensions' => 'ym:s:lastTrafficSource',
            'metrics' => implode(',', $metrics).',ym:s:visits',
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'sort' => '-'.($metrics[0]),
            'limit' => $limit,
        ])['data'] ?? [])
            ->map(function (array $row) use ($goalIds) {
                $dims = $row['dimensions'] ?? [];
                $metrics = $row['metrics'] ?? [];
                $goalCount = count($this->conversionMetrics($goalIds));
                $conversions = 0.0;
                for ($i = 0; $i < $goalCount; $i++) {
                    $conversions += (float) ($metrics[$i] ?? 0);
                }
                $visits = (float) ($metrics[$goalCount] ?? 0);

                return [
                    'label' => $this->translateTrafficSource(
                        isset($dims[0]['id']) ? (string) $dims[0]['id'] : null,
                        isset($dims[0]['name']) ? (string) $dims[0]['name'] : null,
                    ),
                    'conversions' => $conversions,
                    'visits' => $visits,
                    'conversion_rate' => $visits > 0 ? ($conversions / $visits) * 100 : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{url: string, label: string, channels: list<array{label: string, visits: float, users: float, bounce_rate: float}>}>
     */
    public function fetchLandingPagesByChannel(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $pageLimit = 15,
        int $channelLimit = 8,
    ): array {
        return $this->groupPageRowsByChannel(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:startURL,ym:s:lastTrafficSource',
                'metrics' => 'ym:s:visits,ym:s:users,ym:s:bounceRate',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'sort' => '-ym:s:visits',
                'limit' => 10000,
            ]),
            ['visits', 'users', 'bounce_rate'],
            $pageLimit,
            $channelLimit,
        );
    }

    /**
     * @return list<array{url: string, label: string, channels: list<array{label: string, visits: float, bounce_rate: float}>}>
     */
    public function fetchHighBouncePagesByChannel(
        string $accessToken,
        string $counterId,
        string $dateFrom,
        string $dateTo,
        int $pageLimit = 10,
        int $channelLimit = 8,
        int $minVisits = 10,
    ): array {
        return $this->groupPageRowsByChannel(
            $this->statRequest($accessToken, [
                'ids' => $counterId,
                'dimensions' => 'ym:s:startURL,ym:s:lastTrafficSource',
                'metrics' => 'ym:s:visits,ym:s:bounceRate',
                'date1' => $dateFrom,
                'date2' => $dateTo,
                'filters' => "ym:s:visits>={$minVisits}",
                'sort' => '-ym:s:bounceRate',
                'limit' => 10000,
            ]),
            ['visits', 'bounce_rate'],
            $pageLimit,
            $channelLimit,
            sortBy: 'bounce_rate',
        );
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
                $rawLabel = $this->dimensionDateRaw($dims[0] ?? []);
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
        return ReportFetch::remember(
            'metrika.stat.'.sha1($accessToken.'|'.json_encode($params)),
            function () use ($accessToken, $params) {
                $lastBody = null;

                for ($attempt = 0; $attempt < 3; $attempt++) {
                    if ($attempt > 0) {
                        usleep(400000 * $attempt);
                    }

                    $response = Http::withHeaders([
                        'Authorization' => 'OAuth '.$accessToken,
                    ])->get('https://api-metrika.yandex.net/stat/v1/data', array_merge([
                        'lang' => 'ru',
                    ], $params));

                    if ($response->status() === 429) {
                        $lastBody = $response->body();
                        continue;
                    }

                    if (! $response->successful()) {
                        throw new RuntimeException('Metrika API error: '.$response->body());
                    }

                    return $response->json();
                }

                throw new RuntimeException('Metrika API error: '.($lastBody ?: 'rate limit exceeded'));
            },
        );
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
        if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $value, $matches)) {
            return $this->formatMonthLabel($matches[1].'-'.$matches[2]);
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            $months = [
                '01' => 'Янв', '02' => 'Фев', '03' => 'Мар', '04' => 'Апр',
                '05' => 'Май', '06' => 'Июн', '07' => 'Июл', '08' => 'Авг',
                '09' => 'Сен', '10' => 'Окт', '11' => 'Ноя', '12' => 'Дек',
            ];

            return ($months[$matches[2]] ?? $matches[2]).' '.$matches[1];
        }

        if (preg_match('/^(\d{4})(\d{2})$/', $value, $matches)) {
            return $this->formatMonthLabel($matches[1].'-'.$matches[2]);
        }

        return $value;
    }

    /**
     * Метрика может отдавать ym:s:month как name = "3", id = "2026-03-01".
     * Для графиков берём полный идентификатор, чтобы подпись не теряла год.
     *
     * @param  array<string, mixed>  $dimension
     */
    private function dimensionDateRaw(array $dimension): string
    {
        $id = (string) ($dimension['id'] ?? '');
        $name = (string) ($dimension['name'] ?? '');

        foreach ([$id, $name] as $value) {
            if (preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $value) || preg_match('/^\d{6}$/', $value)) {
                return $value;
            }
        }

        return $name !== '' ? $name : ($id !== '' ? $id : '—');
    }

    private function truncateUrl(?string $url): string
    {
        $url = $url ?: '—';
        if (mb_strlen($url) <= 72) {
            return $url;
        }

        return mb_substr($url, 0, 71).'…';
    }

    /** @return array{from: string, to: string} */
    private function monthRange(string $periodEnd, int $months): array
    {
        $end = \Carbon\Carbon::parse($periodEnd)->startOfMonth();

        return [
            'from' => $end->copy()->subMonths($months - 1)->format('Y-m-d'),
            'to' => $end->copy()->endOfMonth()->format('Y-m-d'),
        ];
    }

    /** @return list<string> */
    private function conversionMetrics(?array $goalIds): array
    {
        if ($goalIds === null || $goalIds === []) {
            return ['ym:s:sumGoalReachesAny'];
        }

        return array_map(fn (int $id) => "ym:s:goal{$id}reaches", $goalIds);
    }

    /**
     * @return array{categories: list<string>, series: list<array{name: string, data: list<float>}>}
     */
    private function pivotDateSourceSeries(
        array $payload,
        callable $dateFormatter,
        int $maxSources,
    ): array {
        $matrix = [];
        $dateKeys = [];

        foreach ($payload['data'] ?? [] as $row) {
            $dims = $row['dimensions'] ?? [];
            $dateRaw = $this->dimensionDateRaw($dims[0] ?? []);
            $sourceId = isset($dims[1]['id']) ? (string) $dims[1]['id'] : null;
            $sourceName = isset($dims[1]['name']) ? (string) $dims[1]['name'] : null;
            $sourceLabel = $this->translateTrafficSource($sourceId, $sourceName);
            $visits = (float) ($row['metrics'][0] ?? 0);

            if ($dateRaw === '') {
                continue;
            }

            $dateKeys[$dateRaw] = true;
            $matrix[$sourceLabel][$dateRaw] = ($matrix[$sourceLabel][$dateRaw] ?? 0) + $visits;
        }

        $sortedDates = array_keys($dateKeys);
        sort($sortedDates);
        $categories = array_map(fn (string $d) => $dateFormatter($d), $sortedDates);

        $totals = [];
        foreach ($matrix as $source => $byDate) {
            $totals[$source] = array_sum($byDate);
        }
        arsort($totals);
        $sources = array_slice(array_keys($totals), 0, $maxSources);

        $series = [];
        foreach ($sources as $source) {
            $data = [];
            foreach ($sortedDates as $dateRaw) {
                $data[] = round((float) ($matrix[$source][$dateRaw] ?? 0), 2);
            }
            $series[] = ['name' => $source, 'data' => $data];
        }

        return ['categories' => $categories, 'series' => $series];
    }

    /**
     * @param  list<string>  $metricKeys
     * @return list<array{url: string, label: string, channels: list<array<string, mixed>}>}
     */
    private function groupPageRowsByChannel(
        array $payload,
        array $metricKeys,
        int $pageLimit,
        int $channelLimit,
        string $sortBy = 'visits',
    ): array {
        $pages = [];

        foreach ($payload['data'] ?? [] as $row) {
            $dims = $row['dimensions'] ?? [];
            $metrics = $row['metrics'] ?? [];
            $url = (string) ($dims[0]['name'] ?? $dims[0]['id'] ?? '—');
            $channel = $this->translateTrafficSource(
                isset($dims[1]['id']) ? (string) $dims[1]['id'] : null,
                isset($dims[1]['name']) ? (string) $dims[1]['name'] : null,
            );

            $channelRow = ['label' => $channel];
            foreach ($metricKeys as $index => $key) {
                $channelRow[$key] = (float) ($metrics[$index] ?? 0);
            }

            if (! isset($pages[$url])) {
                $pages[$url] = [
                    'url' => $url,
                    'label' => $this->truncateUrl($url),
                    'channels' => [],
                    '_total' => 0.0,
                ];
            }

            $pages[$url]['channels'][] = $channelRow;
            $pages[$url]['_total'] += (float) ($channelRow['visits'] ?? 0);
        }

        $result = collect($pages)
            ->sortByDesc('_total')
            ->take($pageLimit)
            ->map(function (array $page) use ($channelLimit, $sortBy) {
                $channels = collect($page['channels'])
                    ->sortByDesc($sortBy)
                    ->take($channelLimit)
                    ->values()
                    ->all();

                return [
                    'url' => $page['url'],
                    'label' => $page['label'],
                    'channels' => $channels,
                ];
            })
            ->values()
            ->all();

        return $result;
    }
}
