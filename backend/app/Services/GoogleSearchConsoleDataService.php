<?php

namespace App\Services;

use App\Support\ReportFetch;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleDataService
{
    /** @return array<string, mixed> */
    private function querySearchAnalytics(
        string $accessToken,
        string $siteUrl,
        array $body,
        string $cacheKey,
    ): array {
        $encodedSite = rawurlencode($siteUrl);

        return ReportFetch::remember(
            'gsc.'.$cacheKey.'.'.sha1($accessToken.'|'.$siteUrl.'|'.json_encode($body)),
            function () use ($accessToken, $encodedSite, $body) {
                $response = Http::withToken($accessToken)
                    ->post("https://www.googleapis.com/webmasters/v3/sites/{$encodedSite}/searchAnalytics/query", $body);

                if (! $response->successful()) {
                    throw new RuntimeException('GSC API error: '.$response->body());
                }

                return $response->json();
            },
        );
    }

    /** @return list<array{query: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function fetchTopQueries(
        string $accessToken,
        string $siteUrl,
        string $dateFrom,
        string $dateTo,
        int $limit = 25,
    ): array {
        $payload = $this->querySearchAnalytics($accessToken, $siteUrl, [
            'startDate' => $dateFrom,
            'endDate' => $dateTo,
            'dimensions' => ['query'],
            'rowLimit' => $limit,
        ], 'top-queries');

        return collect($payload['rows'] ?? [])
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
        $payload = $this->querySearchAnalytics($accessToken, $siteUrl, [
            'startDate' => $dateFrom,
            'endDate' => $dateTo,
        ], 'summary');

        $row = $payload['rows'][0] ?? null;
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
        $payload = $this->querySearchAnalytics($accessToken, $siteUrl, [
            'startDate' => $dateFrom,
            'endDate' => $dateTo,
            'dimensions' => ['page'],
            'rowLimit' => $limit,
        ], 'top-pages');

        return collect($payload['rows'] ?? [])
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
