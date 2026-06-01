<?php

namespace App\Services;

use App\Support\ReportFetch;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TopvisorDataService
{
    private const BASE_URL = 'https://api.topvisor.com/v2/json';

    /** @return list<array{id: int, name: string, url: string, on: int, searchers: array<int, mixed>, regions: list<array{index: int, searcher: string, region: string, label: string}>}> */
    public function listProjects(string $userId, string $apiKey): array
    {
        $projects = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->request($userId, $apiKey, 'get', 'projects_2', 'projects', [
                'limit' => $limit,
                'offset' => $offset,
                'fields' => ['id', 'name', 'url', 'on'],
                'show_searchers_and_regions' => 1,
            ]);

            $batch = $response['result'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $project) {
                if (! is_array($project)) {
                    continue;
                }

                $normalized = $this->normalizeProject($project);
                if ($normalized['id'] > 0) {
                    $projects[] = $normalized;
                }
            }

            $nextOffset = $response['nextOffset'] ?? null;
            if ($nextOffset === null) {
                break;
            }

            $offset = (int) $nextOffset;
        } while (true);

        return $projects;
    }

    /** @return list<array{id: string, label: string, meta?: array<string, mixed>}> */
    public function listProjectResources(string $userId, string $apiKey): array
    {
        return collect($this->listProjects($userId, $apiKey))
            ->filter(fn (array $project) => $this->isBindableProject($project))
            ->map(fn (array $project) => [
                'id' => (string) $project['id'],
                'label' => $this->projectResourceLabel($project),
                'meta' => [
                    'project_id' => $project['id'],
                    'name' => $project['name'],
                    'url' => $project['url'],
                    'site' => $project['url'],
                    'project_name' => $this->projectDisplayName($project),
                    'regions' => $project['regions'],
                ],
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function resolveRegionIndex(
        string $userId,
        string $apiKey,
        int $projectId,
        ?int $preferred = null,
    ): int {
        if ($preferred !== null && $preferred > 0) {
            return $preferred;
        }

        $project = collect($this->listProjects($userId, $apiKey))->firstWhere('id', $projectId);
        if (! $project) {
            throw new RuntimeException("Topvisor project {$projectId} not found.");
        }

        foreach ($project['regions'] as $region) {
            $searcher = mb_strtolower($region['searcher']);
            if (str_contains($searcher, 'yandex') || str_contains($searcher, 'яндекс')) {
                return $region['index'];
            }
        }

        $first = $project['regions'][0]['index'] ?? 0;
        if ($first <= 0) {
            throw new RuntimeException("Topvisor project {$projectId} has no tracked regions.");
        }

        return $first;
    }

    /** @var list<array{key: string, label: string}> */
    private const TOP_RANGES = [
        ['key' => '1_3', 'label' => '1–3'],
        ['key' => '1_10', 'label' => '1–10'],
        ['key' => '11_30', 'label' => '11–30'],
        ['key' => '31_50', 'label' => '31–50'],
        ['key' => '51_100', 'label' => '51–100'],
        ['key' => '101_10000', 'label' => '100+'],
    ];

    /**
     * Распределение по ТОПам как в интерфейсе Topvisor (доля, количество, динамика).
     *
     * @return array{
     *     ranges: list<array{label: string, count: int, percent: int|null, delta: int|null}>,
     *     check_dates: array{0: string, 1: string}|null
     * }
     */
    public function fetchTopDistribution(
        string $userId,
        string $apiKey,
        int $projectId,
        int $regionIndex,
        string $dateFrom,
        string $dateTo,
    ): array {
        $response = $this->request($userId, $apiKey, 'get', 'positions_2', 'summary', [
            'project_id' => $projectId,
            'region_index' => $regionIndex,
            'dates' => [$dateFrom, $dateTo],
            'show_tops' => 1,
            'show_dynamics' => 1,
        ]);

        $result = $response['result'] ?? [];
        $topsSeries = $this->normalizeTopsSeries($result['tops'] ?? []);
        $previous = $topsSeries[0] ?? [];
        $latest = $topsSeries[count($topsSeries) - 1] ?? [];
        $totalKeywords = $this->fetchKeywordCount($userId, $apiKey, $projectId);
        $checkDates = $this->formatCheckDates($result['dates'] ?? null);

        $ranges = [];
        foreach (self::TOP_RANGES as $definition) {
            $key = $definition['key'];
            $count = (int) ($latest[$key] ?? 0);
            $delta = count($topsSeries) > 1
                ? $count - (int) ($previous[$key] ?? 0)
                : null;

            $ranges[] = [
                'label' => $definition['label'],
                'count' => $count,
                'percent' => $totalKeywords > 0 ? (int) round(100 * $count / $totalKeywords) : null,
                'delta' => $delta,
            ];
        }

        return [
            'ranges' => $ranges,
            'check_dates' => $checkDates,
        ];
    }

    /** @return array{visibility: float|null, visibility_dynamic: float|null, tops: array<string, int>, avg: float|null} */
    public function fetchSummary(
        string $userId,
        string $apiKey,
        int $projectId,
        int $regionIndex,
        string $dateFrom,
        string $dateTo,
    ): array {
        $response = $this->request($userId, $apiKey, 'get', 'positions_2', 'summary', [
            'project_id' => $projectId,
            'region_index' => $regionIndex,
            'dates' => [$dateFrom, $dateTo],
            'show_visibility' => 1,
            'show_tops' => 1,
            'show_avg' => 1,
            'show_dynamics' => 1,
        ]);

        $result = $response['result'] ?? [];
        $latest = $this->latestTopsSnapshot($result['tops'] ?? []);

        return [
            'visibility' => $this->latestMetric($result['visibilities'] ?? null, $result['visibility'] ?? null),
            'visibility_dynamic' => isset($result['visibility_dynamic']) ? (float) $result['visibility_dynamic'] : null,
            'tops' => [
                'top3' => (int) ($latest['1_3'] ?? 0),
                'top10' => (int) ($latest['1_10'] ?? 0),
                'top30' => (int) ($latest['11_30'] ?? 0),
                'top100' => (int) ($latest['51_100'] ?? 0) + (int) ($latest['101_10000'] ?? 0),
            ],
            'avg' => $this->latestMetric($result['avgs'] ?? null, $result['avg'] ?? null),
        ];
    }

    public function fetchKeywordCount(string $userId, string $apiKey, int $projectId): int
    {
        $response = $this->request($userId, $apiKey, 'get', 'keywords_2', 'keywords', [
            'project_id' => $projectId,
            'limit' => 1,
            'fields' => ['id'],
        ]);

        return max(0, (int) ($response['total'] ?? 0));
    }

    /** @return list<array{keyword: string, position: float|null, previous: float|null, delta: float|null}> */
    public function fetchPositionsTable(
        string $userId,
        string $apiKey,
        int $projectId,
        int $regionIndex,
        string $dateFrom,
        string $dateTo,
        int $limit = 50,
    ): array {
        $response = $this->request($userId, $apiKey, 'get', 'positions_2', 'history', [
            'project_id' => $projectId,
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'type_range' => '0',
            'regions_indexes' => [$regionIndex],
            'positions_fields' => ['position'],
            'limit' => $limit,
        ]);

        $keywords = $response['result']['keywords'] ?? [];
        $rows = [];

        foreach ($keywords as $keyword) {
            $positions = $keyword['positionsData'][0]['positions'] ?? $keyword['positions'] ?? [];
            $values = is_array($positions) ? array_values($positions) : [];
            $current = $this->parsePosition($values[0] ?? null);
            $previous = $this->parsePosition($values[1] ?? null);

            $rows[] = [
                'keyword' => (string) ($keyword['name'] ?? '—'),
                'position' => $current,
                'previous' => $previous,
                'delta' => ($current !== null && $previous !== null) ? ($previous - $current) : null,
            ];
        }

        return $rows;
    }

    /** @return array{project_id: int, region_index: int|null} */
    public function parseBindingResourceId(string $resourceId): array
    {
        if (str_contains($resourceId, ':')) {
            [$projectId, $regionIndex] = explode(':', $resourceId, 2);

            return [
                'project_id' => (int) $projectId,
                'region_index' => (int) $regionIndex,
            ];
        }

        return [
            'project_id' => (int) $resourceId,
            'region_index' => null,
        ];
    }

    /** @param  array<string, mixed>  $project */
    private function normalizeProject(array $project): array
    {
        $name = trim((string) ($project['name'] ?? $project['project_name'] ?? ''));
        $url = trim((string) ($project['url'] ?? $project['site'] ?? $project['host'] ?? ''));

        return [
            'id' => (int) ($project['id'] ?? 0),
            'name' => $name,
            'url' => $url,
            'on' => (int) ($project['on'] ?? 1),
            'searchers' => $project['searchers'] ?? [],
            'regions' => $this->extractRegions($project),
        ];
    }

    /** @return list<array{index: int, searcher: string, region: string, label: string}> */
    private function extractRegions(array $project): array
    {
        $regions = [];
        $seen = [];

        foreach ($project['searchers'] ?? [] as $searcher) {
            $searcherName = (string) ($searcher['name'] ?? 'ПС');
            foreach ($searcher['regions'] ?? [] as $region) {
                $index = (int) ($region['index'] ?? 0);
                if ($index <= 0 || isset($seen[$index])) {
                    continue;
                }

                $seen[$index] = true;
                $regionName = (string) ($region['areaName'] ?? $region['name'] ?? 'Регион');
                $regions[] = [
                    'index' => $index,
                    'searcher' => $searcherName,
                    'region' => $regionName,
                    'label' => $searcherName.' · '.$regionName,
                ];
            }
        }

        return $regions;
    }

    /** @param  array{id: int, name: string, url: string}  $project */
    private function projectDisplayName(array $project): string
    {
        $name = trim($project['name']);
        $url = trim($project['url']);

        if ($name !== '') {
            return $name;
        }

        if ($url !== '') {
            return $url;
        }

        return 'Проект #'.$project['id'];
    }

    /** @param  array{id: int, name: string, url: string}  $project */
    private function projectResourceLabel(array $project): string
    {
        return $this->projectDisplayName($project).' (#'.$project['id'].')';
    }

    private function parsePosition(mixed $value): ?float
    {
        if ($value === null || $value === '--' || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /** @return list<array<string, int>> */
    private function normalizeTopsSeries(mixed $tops): array
    {
        if (! is_array($tops) || $tops === []) {
            return [];
        }

        if (array_is_list($tops)) {
            return array_values(array_filter($tops, fn ($item) => is_array($item)));
        }

        return [$tops];
    }

    /** @return array<string, int> */
    private function latestTopsSnapshot(mixed $tops): array
    {
        $series = $this->normalizeTopsSeries($tops);

        return $series[count($series) - 1] ?? [];
    }

    private function latestMetric(mixed $series, mixed $scalar): ?float
    {
        if (is_array($series) && $series !== []) {
            $values = array_values($series);
            $last = $values[count($values) - 1] ?? null;

            return $last !== null && $last !== '' ? (float) $last : null;
        }

        return $scalar !== null && $scalar !== '' ? (float) $scalar : null;
    }

    /** @return array{0: string, 1: string}|null */
    private function formatCheckDates(mixed $dates): ?array
    {
        if (! is_array($dates) || count($dates) < 2) {
            return null;
        }

        $from = $this->formatReportDate($dates[0]);
        $to = $this->formatReportDate($dates[1]);

        if ($from === '—' || $to === '—') {
            return null;
        }

        return [$from, $to];
    }

    private function formatReportDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd.m.Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->format('d.m.Y');
            }
        }

        $timestamp = strtotime($raw);

        return $timestamp !== false ? date('d.m.Y', $timestamp) : $raw;
    }

    /** @param  array{id: int, on: int, regions: list<array<string, mixed>>}  $project */
    private function isBindableProject(array $project): bool
    {
        if (($project['on'] ?? -1) === -1) {
            return false;
        }

        return ($project['regions'] ?? []) !== [];
    }

    /** @return array<string, mixed> */
    private function request(
        string $userId,
        string $apiKey,
        string $operation,
        string $service,
        string $method,
        array $payload = [],
    ): array {
        $url = self::BASE_URL.'/'.$operation.'/'.$service.'/'.$method;
        $authorization = str_starts_with(strtolower($apiKey), 'bearer ')
            ? $apiKey
            : 'bearer '.$apiKey;

        return ReportFetch::remember(
            'topvisor.'.sha1($userId.'|'.$apiKey.'|'.$operation.'|'.$service.'|'.$method.'|'.json_encode($payload)),
            function () use ($userId, $apiKey, $operation, $service, $method, $payload, $url, $authorization) {
                $response = Http::withHeaders([
                    'User-Id' => $userId,
                    'Authorization' => $authorization,
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                if (! $response->successful()) {
                    throw new RuntimeException('Topvisor API error: '.$response->body());
                }

                $json = $response->json();
                if (isset($json['errors']) && is_array($json['errors']) && $json['errors'] !== []) {
                    $message = collect($json['errors'])->pluck('string')->filter()->first()
                        ?? 'Unknown Topvisor error';

                    throw new RuntimeException('Topvisor API error: '.$message);
                }

                return is_array($json) ? $json : [];
            },
        );
    }
}
