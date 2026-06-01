<?php

namespace App\Services;

use App\Support\KeysSoRateLimiter;
use App\Support\ReportFetch;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeysSoDataService
{
    public function __construct(private KeysSoRateLimiter $rateLimiter) {}

    /** @return list<array{id: int, name: string, tracking_item: string, search_settings: list<array<string, mixed>>}> */
    public function listMonitoringProjects(string $token): array
    {
        $projects = [];
        $page = 1;
        $perPage = 25;

        while (true) {
            $response = $this->request($token, 'get', '/monitoring', [
                'page' => $page,
                'per_page' => $perPage,
            ], cache: false);

            $batch = $this->extractMonitoringBatch($response);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $project) {
                if (! is_array($project)) {
                    continue;
                }

                $normalized = $this->normalizeProject($project);
                if ($normalized['id'] > 0) {
                    $projects[$normalized['id']] = $normalized;
                }
            }

            $total = (int) ($response['total'] ?? 0);
            if ($total > 0 && count($projects) >= $total) {
                break;
            }

            $lastPage = (int) ($response['last_page'] ?? 0);
            if ($lastPage > 0) {
                if ($page >= $lastPage) {
                    break;
                }
            } elseif (count($batch) < $perPage) {
                break;
            }

            $page++;
        }

        return array_values($projects);
    }

    /** @return list<array{project_id: int, title: string, domain: string}> */
    public function listDashboardProjects(string $token): array
    {
        $response = $this->request($token, 'get', '/projects', [], cache: false);
        $items = array_is_list($response) ? $response : ($response['data'] ?? []);

        if (! is_array($items)) {
            return [];
        }

        $projects = [];
        foreach ($items as $project) {
            if (! is_array($project)) {
                continue;
            }

            $projectId = (int) ($project['projectId'] ?? $project['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            $domain = (string) ($project['domain']['name'] ?? $project['domain'] ?? '');
            if (is_array($project['domain'] ?? null)) {
                $domain = (string) (($project['domain']['name'] ?? '') ?: $domain);
            }

            $projects[] = [
                'project_id' => $projectId,
                'title' => trim((string) ($project['title'] ?? $project['name'] ?? $domain)),
                'domain' => trim($domain),
            ];
        }

        return $projects;
    }

    /** @return list<array{id: string, label: string, meta?: array<string, mixed>}> */
    public function listProjectResources(string $token): array
    {
        $monitoring = array_map(
            fn (array $project) => $this->enrichMonitoringProject($token, $project),
            $this->listMonitoringProjects($token),
        );
        $resources = [];

        foreach ($monitoring as $project) {
            $resources[] = [
                'id' => (string) $project['id'],
                'label' => $this->projectResourceLabel($project),
                'meta' => [
                    'resource_kind' => 'monitoring',
                    'supports_positions' => true,
                    'project_id' => $project['id'],
                    'name' => $project['name'],
                    'site' => $project['tracking_item'],
                    'url' => $project['tracking_item'],
                    'project_name' => $project['name'],
                    'search_settings' => $project['search_settings'],
                ],
            ];
        }

        $seenDomains = collect($monitoring)
            ->pluck('tracking_item')
            ->map(fn (string $domain) => $this->normalizeDomain($domain))
            ->filter()
            ->all();

        foreach ($this->listDashboardProjects($token) as $project) {
            $domain = $this->normalizeDomain($project['domain']);
            if ($domain !== '' && in_array($domain, $seenDomains, true)) {
                continue;
            }

            $resources[] = [
                'id' => 'dashboard:'.$project['project_id'],
                'label' => $project['title'].' (дашборд · #'.$project['project_id'].')',
                'meta' => [
                    'resource_kind' => 'dashboard',
                    'supports_positions' => false,
                    'project_id' => $project['project_id'],
                    'name' => $project['title'],
                    'site' => $project['domain'],
                    'url' => $project['domain'],
                    'project_name' => $project['title'],
                    'search_settings' => [],
                ],
            ];
        }

        return collect($resources)
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function fetchProjectEntity(string $token, int $projectId): array
    {
        return $this->request($token, 'get', "/monitoring/{$projectId}/entity");
    }

    /**
     * @param  array{regionId: int, engine: int}  $searchSettings
     * @return array{
     *     visibility: float|null,
     *     visibility_dynamic: float|null,
     *     tops: array<string, int>,
     *     avg: float|null
     * }
     */
    public function fetchSummary(
        string $token,
        int $projectId,
        array $searchSettings,
        string $dateFrom,
        string $dateTo,
    ): array {
        $response = $this->request($token, 'get', "/monitoring/{$projectId}/report-chart", [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'searchSettings' => [json_encode($searchSettings, JSON_UNESCAPED_UNICODE)],
        ]);

        $data = $response['data'] ?? [];
        if (! is_array($data) || $data === []) {
            return $this->emptySummary();
        }

        ksort($data);
        $dates = array_values($data);
        $latest = is_array($dates[count($dates) - 1] ?? null) ? $dates[count($dates) - 1] : null;
        $previous = count($dates) > 1 && is_array($dates[count($dates) - 2] ?? null)
            ? $dates[count($dates) - 2]
            : null;

        if (! $latest) {
            return $this->emptySummary();
        }

        $totalWords = max(1, (int) ($latest['total_words'] ?? 0));
        $top10 = (int) ($latest['it10_organic'] ?? 0);
        $visibility = round(($top10 / $totalWords) * 100, 1);

        $previousVisibility = null;
        if ($previous) {
            $previousTotal = max(1, (int) ($previous['total_words'] ?? 0));
            $previousTop10 = (int) ($previous['it10_organic'] ?? 0);
            $previousVisibility = round(($previousTop10 / $previousTotal) * 100, 1);
        }

        return [
            'visibility' => $visibility,
            'visibility_dynamic' => $previousVisibility !== null ? round($visibility - $previousVisibility, 1) : null,
            'tops' => [
                'top3' => (int) ($latest['it3_organic'] ?? 0),
                'top10' => $top10,
                'top30' => (int) ($latest['it50_organic'] ?? 0),
                'top100' => (int) ($latest['it100_organic'] ?? 0),
            ],
            'avg' => isset($latest['day_avg_organic_pos']) ? (float) $latest['day_avg_organic_pos'] : null,
        ];
    }

    /**
     * @param  array{regionId: int, engine: int}  $searchSettings
     * @return list<array{keyword: string, position: float|null, previous: float|null, delta: float|null}>
     */
    public function fetchPositionsTable(
        string $token,
        int $projectId,
        array $searchSettings,
        string $dateFrom,
        string $dateTo,
        int $limit = 50,
        ?string $trackingDomain = null,
    ): array {
        $response = $this->request($token, 'get', "/monitoring/{$projectId}/report", [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'page' => 1,
            'per_page' => max(1, min(200, $limit)),
            'sort' => 'superwsk|desc',
            'searchSettings' => [json_encode($searchSettings, JSON_UNESCAPED_UNICODE)],
        ]);

        $words = $response['data']['words'] ?? [];
        if (! is_array($words)) {
            return [];
        }

        $rows = [];
        foreach ($words as $wordData) {
            if (! is_array($wordData)) {
                continue;
            }

            $keyword = (string) ($wordData['word'] ?? '—');
            $domainData = $this->resolveTrackedDomainData($wordData['domains'] ?? [], $trackingDomain);
            if (! $domainData) {
                continue;
            }

            $dates = $this->extractPositionDates($domainData);
            $current = $this->parsePosition($dates[0]['organic_pos'] ?? null);
            $previous = $this->parsePosition($dates[1]['organic_pos'] ?? null);

            $rows[] = [
                'keyword' => $keyword,
                'position' => $current,
                'previous' => $previous,
                'delta' => ($current !== null && $previous !== null) ? ($previous - $current) : null,
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    public function domainFromProject(?string $domain): ?string
    {
        $normalized = $this->normalizeDomain($domain ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array{id: int, name: string, tracking_item: string, search_settings: list<array<string, mixed>>}|null
     */
    public function findMonitoringProjectByDomain(string $token, string $domain): ?array
    {
        $needle = $this->normalizeDomain($domain);
        if ($needle === '') {
            return null;
        }

        foreach ($this->listMonitoringProjects($token) as $project) {
            if ($this->normalizeDomain($project['tracking_item']) === $needle) {
                return $this->enrichMonitoringProject($token, $project);
            }
        }

        return null;
    }

    /**
     * Сводка «Запросы сайта» — как на дашборде Keys.so (топы + ИИ).
     *
     * @return array{
     *     top1: int,
     *     top3: int,
     *     top5: int,
     *     top10: int,
     *     top50: int,
     *     ai_mentions: int,
     *     keywords: list<array{keyword: string, position: float|null, frequency: float}>
     * }
     */
    public function fetchSiteQueriesDashboard(
        string $token,
        string $domain,
        string $base = 'msk',
        int $keywordsLimit = 10,
    ): array {
        $payload = $this->fetchDomainDashboardPayload($token, $domain, $base);

        return [
            'top1' => (int) ($payload['it1'] ?? 0),
            'top3' => (int) ($payload['it3'] ?? 0),
            'top5' => (int) ($payload['it5'] ?? 0),
            'top10' => (int) ($payload['it10'] ?? 0),
            'top50' => (int) ($payload['it50'] ?? 0),
            'ai_mentions' => (int) ($payload['aiAnswersCnt'] ?? $payload['ai_answers_cnt'] ?? 0),
            'keywords' => $this->mapDashboardKeywords($payload['keys'] ?? [], $keywordsLimit),
        ];
    }

    /**
     * Сводка «Ссылки» — как на дашборде Keys.so.
     *
     * @return array{
     *     incoming: int,
     *     outgoing: int,
     *     dr: int|null,
     *     referring_domains: int,
     *     outgoing_domains: int,
     *     links_by_ip: int,
     *     anchors: int
     * }
     */
    public function fetchLinksDashboard(string $token, string $domain): array
    {
        $normalized = $this->normalizeDomain($domain);
        $dashboard = $this->fetchDomainDashboardPayload($token, $domain, 'msk');

        return [
            'incoming' => $this->fetchLinksReportTotal($token, '/report/simple/links/backlinks', $normalized),
            'outgoing' => $this->fetchLinksReportTotal($token, '/report/simple/links/outlinks', $normalized),
            'dr' => isset($dashboard['dr']) ? (int) $dashboard['dr'] : null,
            'referring_domains' => $this->fetchLinksReportTotal($token, '/report/simple/links/backlinks-domains', $normalized),
            'outgoing_domains' => $this->fetchLinksReportTotal($token, '/report/simple/links/outlinks-domains', $normalized),
            'links_by_ip' => $this->fetchLinksReportTotal($token, '/report/simple/links/backlinks-ip', $normalized),
            'anchors' => $this->fetchLinksReportTotal($token, '/report/simple/links/backlinks-anchor', $normalized),
        ];
    }

    /**
     * @return list<array{query: string, answer: string, date: string}>
     */
    public function fetchDashboardAiMentions(
        string $token,
        string $domain,
        int $limit = 25,
    ): array {
        $normalized = $this->normalizeDomain($domain);
        $this->waitForAiReport($token, $normalized);

        $payload = $this->requestReport($token, '/report/simple/organic/ai-answers', [
            'base' => 'msk',
            'domain' => $normalized,
            'page' => 1,
            'per_page' => max(1, min(100, $limit)),
            'sort' => 'superwsk|desc',
        ]);

        return collect($payload['data'] ?? [])
            ->map(fn (array $row) => [
                'query' => (string) ($row['query_text'] ?? $row['word'] ?? '—'),
                'answer' => $this->extractAiAnswerText($row),
                'date' => $this->formatReportDate($row['created_at'] ?? $row['updated_at'] ?? null),
            ])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $row */
    private function extractAiAnswerText(array $row): string
    {
        $raw = $row['ai_answer']
            ?? $row['ai_content']
            ?? $row['aiContent']
            ?? $row['query_answer']
            ?? '';

        if (is_array($raw)) {
            $raw = implode("\n", array_map('strval', $raw));
        }

        $text = $this->htmlToPlainText((string) $raw);

        return $text !== '' ? $this->truncate($text, 500) : '—';
    }

    private function htmlToPlainText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/li>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/p>/i', "\n\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return array<string, mixed> */
    private function fetchDomainDashboardPayload(string $token, string $domain, string $base = 'msk'): array
    {
        $payload = $this->requestReport($token, '/report/simple/domain_dashboard', [
            'base' => $base,
            'domain' => $this->normalizeDomain($domain),
        ]);

        return is_array($payload) ? $payload : [];
    }

    private function fetchLinksReportTotal(string $token, string $path, string $domain): int
    {
        $payload = $this->requestReport($token, $path, [
            'domain' => $domain,
            'page' => 1,
            'per_page' => 1,
        ]);

        return (int) ($payload['total'] ?? 0);
    }

    /**
     * @param  list<mixed>  $keys
     * @return list<array{keyword: string, position: float|null, frequency: float}>
     */
    private function mapDashboardKeywords(array $keys, int $limit): array
    {
        return collect($keys)
            ->filter(fn ($row) => is_array($row))
            ->take(max(1, min(50, $limit)))
            ->map(fn (array $row) => [
                'keyword' => (string) ($row['word'] ?? '—'),
                'position' => $this->parsePosition($row['pos'] ?? null),
                'frequency' => (float) ($row['wsk'] ?? $row['ws'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return array{project_id: int, region_id: int|null, engine: int|null} */
    public function parseBindingResourceId(string $resourceId): array
    {
        if (str_starts_with($resourceId, 'dashboard:')) {
            throw new RuntimeException('Выберите проект мониторинга Keys.so, а не дашборда.');
        }

        $parts = explode(':', $resourceId);

        return [
            'project_id' => (int) ($parts[0] ?? 0),
            'region_id' => isset($parts[1]) ? (int) $parts[1] : null,
            'engine' => isset($parts[2]) ? (int) $parts[2] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $searchSettings
     * @return array{regionId: int, engine: int}
     */
    public function resolveSearchSettings(array $searchSettings, ?int $preferredRegionId = null, ?int $preferredEngine = null): array
    {
        if ($preferredRegionId !== null) {
            foreach ($searchSettings as $setting) {
                $regionId = (int) ($setting['region_id'] ?? $setting['regionId'] ?? 0);
                $engine = (int) ($setting['search_engine'] ?? $setting['engine'] ?? 0);
                if ($regionId === $preferredRegionId && ($preferredEngine === null || $engine === $preferredEngine)) {
                    return ['regionId' => $regionId, 'engine' => $engine];
                }
            }

            return [
                'regionId' => $preferredRegionId,
                'engine' => $preferredEngine ?? 0,
            ];
        }

        foreach ($searchSettings as $setting) {
            $engine = (int) ($setting['search_engine'] ?? $setting['engine'] ?? -1);
            if ($engine === 0) {
                return [
                    'regionId' => (int) ($setting['region_id'] ?? $setting['regionId'] ?? 0),
                    'engine' => 0,
                ];
            }
        }

        $first = $searchSettings[0] ?? [];

        return [
            'regionId' => (int) ($first['region_id'] ?? $first['regionId'] ?? 0),
            'engine' => (int) ($first['search_engine'] ?? $first['engine'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function requestReport(string $token, string $path, array $query): array
    {
        return $this->request($token, 'get', $path, $query, cache: true, allowAccepted: true);
    }

    private function waitForAiReport(string $token, string $domain, int $maxAttempts = 12): void
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $state = $this->request($token, 'get', '/report/simple/ai-answers/state', [
                'base' => 'msk',
                'domain' => $domain,
            ], cache: false, allowAccepted: true);

            $aiState = (int) ($state['ai_state'] ?? 0);
            if ($aiState >= 10) {
                return;
            }

            sleep(5);
        }
    }

    /** @return array<string, mixed> */
    private function request(
        string $token,
        string $method,
        string $path,
        array $query = [],
        bool $cache = true,
        bool $allowAccepted = false,
    ): array {
        $url = rtrim((string) config('keysso.base_url'), '/').'/'.ltrim($path, '/');

        $fetch = function () use ($token, $method, $url, $query, $allowAccepted) {
            $acceptedPolls = 0;

            for ($attempt = 0; $attempt < 8; $attempt++) {
                $this->rateLimiter->waitForSlot();

                $pending = Http::withHeaders([
                    'X-Keyso-TOKEN' => $token,
                    'Accept' => 'application/json',
                ])->timeout(60);

                $response = $method === 'get'
                    ? $pending->get($url, $query)
                    : $pending->send(strtoupper($method), $url, ['query' => $query]);

                if ($response->status() === 429) {
                    sleep(max(1, (int) $response->header('Retry-After', 2)));

                    continue;
                }

                if ($response->status() === 401) {
                    throw new RuntimeException('Неверный API-токен Keys.so.');
                }

                if ($allowAccepted && $response->status() === 202) {
                    $acceptedPolls++;
                    if ($acceptedPolls >= 15) {
                        throw new RuntimeException('Keys.so: отчёт не успел построиться за отведённое время.');
                    }
                    sleep(5);

                    continue;
                }

                if (! $response->successful()) {
                    $message = $response->json('message') ?? $response->body();
                    throw new RuntimeException('Keys.so API error: '.$message);
                }

                $json = $response->json();

                return is_array($json) ? $json : [];
            }

            throw new RuntimeException('Keys.so API rate limit exceeded.');
        };

        if (! $cache) {
            return $fetch();
        }

        return ReportFetch::remember(
            'keysso.'.sha1($token.'|'.$method.'|'.$path.'|'.json_encode($query)),
            $fetch,
        );
    }

    /** @return list<array<string, mixed>> */
    private function extractMonitoringBatch(array $response): array
    {
        $data = $response['data'] ?? [];
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        return [];
    }

    private function normalizeDomain(string $domain): string
    {
        $value = mb_strtolower(trim($domain));
        $value = preg_replace('#^https?://#', '', $value) ?? $value;
        $value = preg_replace('#^www\.#', '', $value) ?? $value;

        return rtrim($value, '/');
    }

    /** @param  array<string, mixed>  $project */
    private function normalizeProject(array $project): array
    {
        $searchSettings = [];
        foreach ($project['search_settings'] ?? [] as $setting) {
            if (! is_array($setting)) {
                continue;
            }

            $regionId = (int) ($setting['region_id'] ?? $setting['regionId'] ?? 0);
            $engine = (int) ($setting['search_engine'] ?? $setting['engine'] ?? 0);
            if ($regionId <= 0) {
                continue;
            }

            $searchSettings[] = [
                'region_id' => $regionId,
                'region_name' => (string) ($setting['region_name'] ?? $setting['regionName'] ?? 'Регион'),
                'engine' => $engine,
                'engine_name' => (string) ($setting['engine_name'] ?? $setting['engineName'] ?? 'Поисковая система'),
                'label' => trim(
                    ((string) ($setting['engine_name'] ?? $setting['engineName'] ?? 'ПС'))
                    .' · '
                    .((string) ($setting['region_name'] ?? $setting['regionName'] ?? 'Регион')),
                ),
            ];
        }

        return [
            'id' => (int) ($project['id'] ?? 0),
            'name' => trim((string) ($project['name'] ?? '')),
            'tracking_item' => trim((string) ($project['trackingItem'] ?? $project['tracking_item'] ?? '')),
            'search_settings' => $searchSettings,
        ];
    }

    /**
     * List endpoint omits trackingItem; entity has the tracked domain.
     *
     * @param  array{id: int, name: string, tracking_item: string, search_settings: list<array<string, mixed>>}  $project
     * @return array{id: int, name: string, tracking_item: string, search_settings: list<array<string, mixed>>}
     */
    private function enrichMonitoringProject(string $token, array $project): array
    {
        if ($project['id'] <= 0 || ($project['tracking_item'] !== '' && $project['search_settings'] !== [])) {
            return $project;
        }

        $entity = $this->fetchProjectEntity($token, $project['id']);

        if ($project['tracking_item'] === '') {
            $project['tracking_item'] = trim((string) ($entity['trackingItem'] ?? $entity['tracking_item'] ?? ''));
        }

        if ($project['search_settings'] === []) {
            $project = $this->normalizeProject([
                'id' => $project['id'],
                'name' => $project['name'],
                'trackingItem' => $project['tracking_item'],
                'search_settings' => $entity['searchSettings'] ?? $entity['search_settings'] ?? [],
            ]);
        }

        return $project;
    }

    /** @param  array{id: int, name: string, tracking_item: string}  $project */
    private function projectResourceLabel(array $project): string
    {
        $domain = $project['tracking_item'];
        $name = $project['name'] !== '' ? $project['name'] : $domain;

        if ($name === '') {
            return 'Проект #'.$project['id'];
        }

        if ($domain !== '' && $domain !== $name && ! str_contains(mb_strtolower($name), mb_strtolower($domain))) {
            return $name.' · '.$domain.' (#'.$project['id'].')';
        }

        return $name.' (#'.$project['id'].')';
    }

    /** @return array{visibility: null, visibility_dynamic: null, tops: array<string, int>, avg: null} */
    private function emptySummary(): array
    {
        return [
            'visibility' => null,
            'visibility_dynamic' => null,
            'tops' => [
                'top3' => 0,
                'top10' => 0,
                'top30' => 0,
                'top100' => 0,
            ],
            'avg' => null,
        ];
    }

    /** @param  array<string, mixed>  $domains */
    private function resolveTrackedDomainData(array $domains, ?string $trackingDomain): ?array
    {
        if ($domains === []) {
            return null;
        }

        if ($trackingDomain) {
            $normalized = mb_strtolower($trackingDomain);
            foreach ($domains as $domain => $domainData) {
                if (! is_array($domainData)) {
                    continue;
                }

                if (mb_strtolower((string) $domain) === $normalized || (int) ($domainData['is_competitor'] ?? 1) === 0) {
                    return $domainData;
                }
            }
        }

        foreach ($domains as $domainData) {
            if (is_array($domainData) && (int) ($domainData['is_competitor'] ?? 1) === 0) {
                return $domainData;
            }
        }

        $first = reset($domains);

        return is_array($first) ? $first : null;
    }

    /** @return list<array<string, mixed>> */
    private function extractPositionDates(array $domainData): array
    {
        $engines = $domainData['engines'] ?? [];
        if (! is_array($engines) || $engines === []) {
            return [];
        }

        $engine = $engines[0];
        $regions = is_array($engine['regions'] ?? null) ? $engine['regions'] : [];
        $region = reset($regions);
        if (! is_array($region)) {
            return [];
        }

        $dates = $region['dates'] ?? [];

        return is_array($dates) ? array_values($dates) : [];
    }

    private function parsePosition(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === '--') {
            return null;
        }

        return (float) $value;
    }

    private function formatReportDate(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '—';
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d', 'd.m.Y H:i:s', 'd.m.Y'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed->format('d.m.Y');
            }
        }

        $timestamp = strtotime($raw);

        return $timestamp !== false ? date('d.m.Y', $timestamp) : $raw;
    }

    private function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length - 1).'…';
    }

    private function absoluteUrl(string $domain, string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $host = $this->normalizeDomain($domain);

        return 'https://'.$host.'/'.ltrim($path, '/');
    }
}
