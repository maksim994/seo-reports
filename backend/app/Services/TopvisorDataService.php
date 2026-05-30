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

        return [
            'visibility' => isset($result['visibility']) ? (float) $result['visibility'] : null,
            'visibility_dynamic' => isset($result['visibility_dynamic']) ? (float) $result['visibility_dynamic'] : null,
            'tops' => [
                'top3' => (int) ($result['tops']['top3'] ?? $result['top3'] ?? 0),
                'top10' => (int) ($result['tops']['top10'] ?? $result['top10'] ?? 0),
                'top30' => (int) ($result['tops']['top30'] ?? $result['top30'] ?? 0),
                'top100' => (int) ($result['tops']['top100'] ?? $result['top100'] ?? 0),
            ],
            'avg' => isset($result['avg']) ? (float) $result['avg'] : null,
        ];
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
