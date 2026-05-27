<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TopvisorDataService
{
    private const BASE_URL = 'https://api.topvisor.com/v2/json';

    /** @return list<array{id: int, name: string, site: string, searchers: array<int, mixed>}> */
    public function listProjects(string $userId, string $apiKey): array
    {
        $response = $this->request($userId, $apiKey, 'get', 'projects_2', 'projects', [
            'show_searchers_and_regions' => 1,
        ]);

        return collect($response['result'] ?? [])
            ->map(fn (array $project) => [
                'id' => (int) ($project['id'] ?? 0),
                'name' => (string) ($project['name'] ?? ''),
                'site' => (string) ($project['site'] ?? ''),
                'searchers' => $project['searchers'] ?? [],
            ])
            ->values()
            ->all();
    }

    /** @return list<array{id: string, label: string, meta?: array<string, mixed>}> */
    public function listBindableResources(string $userId, string $apiKey): array
    {
        $resources = [];

        foreach ($this->listProjects($userId, $apiKey) as $project) {
            foreach ($project['searchers'] as $searcher) {
                $searcherName = (string) ($searcher['name'] ?? 'ПС');
                foreach ($searcher['regions'] ?? [] as $region) {
                    $regionIndex = (int) ($region['index'] ?? 0);
                    $regionName = (string) ($region['areaName'] ?? $region['name'] ?? 'Регион');

                    $resources[] = [
                        'id' => $project['id'].':'.$regionIndex,
                        'label' => $project['name'].' · '.$searcherName.' · '.$regionName,
                        'meta' => [
                            'project_id' => $project['id'],
                            'region_index' => $regionIndex,
                            'site' => $project['site'],
                        ],
                    ];
                }
            }
        }

        return $resources;
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

    /** @return array{project_id: int, region_index: int} */
    public function parseBindingResourceId(string $resourceId): array
    {
        if (! str_contains($resourceId, ':')) {
            return ['project_id' => (int) $resourceId, 'region_index' => 0];
        }

        [$projectId, $regionIndex] = explode(':', $resourceId, 2);

        return [
            'project_id' => (int) $projectId,
            'region_index' => (int) $regionIndex,
        ];
    }

    private function parsePosition(mixed $value): ?float
    {
        if ($value === null || $value === '--' || $value === '') {
            return null;
        }

        return (float) $value;
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
    }
}
