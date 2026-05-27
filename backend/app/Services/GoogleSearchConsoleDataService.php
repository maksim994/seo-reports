<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleDataService
{
    /** @return list<array{query: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function fetchTopQueries(
        string $accessToken,
        string $siteUrl,
        string $dateFrom,
        string $dateTo,
        int $limit = 25,
    ): array {
        $encodedSite = rawurlencode($siteUrl);
        $response = Http::withToken($accessToken)
            ->post("https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query", [
                'startDate' => $dateFrom,
                'endDate' => $dateTo,
                'dimensions' => ['query'],
                'rowLimit' => $limit,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GSC API error: '.$response->body());
        }

        return collect($response->json('rows') ?? [])
            ->map(fn (array $row) => [
                'query' => $row['keys'][0] ?? '—',
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0) * 100,
                'position' => (float) ($row['position'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return array{clicks: float, impressions: float, ctr: float, position: float}|null */
    public function fetchPerformanceSummary(
        string $accessToken,
        string $siteUrl,
        string $dateFrom,
        string $dateTo,
    ): ?array {
        $encodedSite = rawurlencode($siteUrl);
        $response = Http::withToken($accessToken)
            ->post("https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query", [
                'startDate' => $dateFrom,
                'endDate' => $dateTo,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GSC API error: '.$response->body());
        }

        $row = $response->json('rows.0');
        if (! $row) {
            return null;
        }

        return [
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0) * 100,
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    /** @return list<array{page: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function fetchTopPages(
        string $accessToken,
        string $siteUrl,
        string $dateFrom,
        string $dateTo,
        int $limit = 25,
    ): array {
        $encodedSite = rawurlencode($siteUrl);
        $response = Http::withToken($accessToken)
            ->post("https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query", [
                'startDate' => $dateFrom,
                'endDate' => $dateTo,
                'dimensions' => ['page'],
                'rowLimit' => $limit,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GSC API error: '.$response->body());
        }

        return collect($response->json('rows') ?? [])
            ->map(fn (array $row) => [
                'page' => $row['keys'][0] ?? '—',
                'clicks' => (float) ($row['clicks'] ?? 0),
                'impressions' => (float) ($row['impressions'] ?? 0),
                'ctr' => (float) ($row['ctr'] ?? 0) * 100,
                'position' => (float) ($row['position'] ?? 0),
            ])
            ->values()
            ->all();
    }
}
