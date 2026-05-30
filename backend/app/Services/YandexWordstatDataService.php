<?php

namespace App\Services;

use App\Support\ReportFetch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexWordstatDataService
{
    private const BASE_URL = 'https://api.wordstat.yandex.net';

    /** @return list<array{label: string, value: float}> */
    public function fetchDynamicsSeries(
        string $accessToken,
        string $phrase,
        string $period,
        CarbonInterface $endDate,
        int $lookbackMonths = 24,
        ?int $regionId = null,
    ): array {
        [$fromDate, $toDate] = $this->buildDynamicsRange($period, $lookbackMonths, $endDate);

        $payload = $this->post($accessToken, '/v1/dynamics', array_filter([
            'phrase' => $phrase,
            'period' => $period,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'regions' => $regionId ? [$regionId] : null,
        ], fn ($value) => $value !== null));

        return collect($payload['dynamics'] ?? [])
            ->map(fn (array $item) => [
                'label' => $this->formatDynamicsLabel((string) ($item['date'] ?? ''), $period),
                'value' => (float) ($item['count'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchTopRequests(
        string $accessToken,
        string $phrase,
        ?int $regionId = null,
        int $limit = 10,
    ): array {
        $payload = $this->post($accessToken, '/v1/topRequests', array_filter([
            'phrase' => $phrase,
            'regions' => $regionId ? [$regionId] : null,
        ], fn ($value) => $value !== null));

        return collect($payload['topRequests'] ?? [])
            ->take($limit)
            ->map(fn (array $item) => [
                'label' => (string) ($item['phrase'] ?? '—'),
                'value' => (float) ($item['count'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, value: float, share: float, affinity: float}> */
    public function fetchRegions(
        string $accessToken,
        string $phrase,
        string $regionType = 'all',
        int $limit = 10,
    ): array {
        $payload = $this->post($accessToken, '/v1/regions', [
            'phrase' => $phrase,
            'regionType' => $regionType,
        ]);

        $labels = $this->regionLabels($accessToken);

        return collect($payload['regions'] ?? [])
            ->take($limit)
            ->map(function (array $item) use ($labels) {
                $regionId = (int) ($item['regionId'] ?? 0);

                return [
                    'label' => $labels[$regionId] ?? ('Регион '.$regionId),
                    'value' => (float) ($item['count'] ?? 0),
                    'share' => round(((float) ($item['share'] ?? 0)) * 100, 2),
                    'affinity' => round((float) ($item['affinityIndex'] ?? 0), 1),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{0: string, 1: string} */
    public function buildDynamicsRange(string $period, int $lookbackMonths, CarbonInterface $endDate): array
    {
        $end = Carbon::parse($endDate->format('Y-m-d'));

        return match ($period) {
            'weekly' => [
                $end->copy()->subWeeks(max(1, $lookbackMonths * 4))->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
                $end->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d'),
            ],
            'daily' => [
                $end->copy()->subDays(59)->format('Y-m-d'),
                $end->copy()->subDay()->format('Y-m-d'),
            ],
            default => [
                $end->copy()->subMonths(max(1, $lookbackMonths) - 1)->startOfMonth()->format('Y-m-d'),
                $end->copy()->endOfMonth()->format('Y-m-d'),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function post(string $accessToken, string $path, array $body): array
    {
        $cacheKey = 'wordstat.'.sha1($accessToken.'|'.$path.'|'.json_encode($body));

        return ReportFetch::remember($cacheKey, function () use ($accessToken, $path, $body) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post(self::BASE_URL.$path, $body);

            if (! $response->successful()) {
                throw new RuntimeException('Wordstat API error: '.$response->body());
            }

            return $response->json() ?? [];
        });
    }

    /** @return array<int, string> */
    private function regionLabels(string $accessToken): array
    {
        return ReportFetch::remember(
            'wordstat.regions-tree.'.sha1($accessToken),
            function () use ($accessToken) {
                $tree = $this->post($accessToken, '/v1/getRegionsTree', []);

                return $this->flattenRegionTree($tree);
            },
        );
    }

    /** @return array<int, string> */
    private function flattenRegionTree(array $node, array $labels = []): array
    {
        if (isset($node['id'], $node['label'])) {
            $labels[(int) $node['id']] = (string) $node['label'];
        }

        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $labels = $this->flattenRegionTree($child, $labels);
            }
        }

        foreach ($node as $key => $value) {
            if ($key === 'children' || ! is_array($value)) {
                continue;
            }

            if (isset($value['id'], $value['label'])) {
                $labels = $this->flattenRegionTree($value, $labels);
            }
        }

        return $labels;
    }

    private function formatDynamicsLabel(string $date, string $period): string
    {
        if ($date === '') {
            return '—';
        }

        $parsed = Carbon::parse($date);

        return match ($period) {
            'monthly' => $parsed->translatedFormat('M Y'),
            'weekly' => $parsed->format('d.m').' – '.$parsed->copy()->endOfWeek(Carbon::SUNDAY)->format('d.m.Y'),
            default => $parsed->format('d.m.Y'),
        };
    }
}
