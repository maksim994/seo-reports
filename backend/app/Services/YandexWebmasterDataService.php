<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class YandexWebmasterDataService
{
    /** @return list<array{query: string, shows: float, clicks: float, ctr: float}> */
    public function fetchPopularQueries(
        string $accessToken,
        string $hostId,
        string $dateFrom,
        string $dateTo,
        int $limit = 25,
    ): array {
        $userId = $this->fetchUserId($accessToken);
        $query = http_build_query([
            'order_by' => 'TOTAL_SHOWS',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'limit' => $limit,
        ]).'&query_indicator=TOTAL_SHOWS&query_indicator=TOTAL_CLICKS';

        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get("https://api.webmaster.yandex.net/v4/user/{$userId}/hosts/{$hostId}/search-queries/popular?{$query}");

        if (! $response->successful()) {
            throw new RuntimeException('Webmaster API error: '.$response->body());
        }

        return collect($response->json('queries') ?? [])
            ->map(function (array $item) {
                $indicators = $item['indicators'] ?? [];
                $shows = (float) ($indicators['TOTAL_SHOWS'] ?? 0);
                $clicks = (float) ($indicators['TOTAL_CLICKS'] ?? 0);

                return [
                    'query' => $item['query_text'] ?? '—',
                    'shows' => $shows,
                    'clicks' => $clicks,
                    'ctr' => $this->computeCtr($clicks, $shows),
                ];
            })
            ->values()
            ->all();
    }

    private function fetchUserId(string $accessToken): int
    {
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get('https://api.webmaster.yandex.net/v4/user');

        if (! $response->successful()) {
            throw new RuntimeException('Webmaster user API error: '.$response->body());
        }

        return (int) $response->json('user_id');
    }

    private function computeCtr(float $clicks, float $shows): float
    {
        if ($shows <= 0) {
            return 0;
        }

        return ($clicks / $shows) * 100;
    }

    /** @return array{shows: float, clicks: float, ctr: float}|null */
    public function fetchSearchSummary(
        string $accessToken,
        string $hostId,
        string $dateFrom,
        string $dateTo,
    ): ?array {
        $userId = $this->fetchUserId($accessToken);
        $query = http_build_query([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]).'&query_indicator=TOTAL_SHOWS&query_indicator=TOTAL_CLICKS';

        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get("https://api.webmaster.yandex.net/v4/user/{$userId}/hosts/{$hostId}/search-queries/all/history?{$query}");

        if (! $response->successful()) {
            throw new RuntimeException('Webmaster API error: '.$response->body());
        }

        $shows = 0.0;
        $clicks = 0.0;
        foreach ($response->json('history') ?? [] as $point) {
            $indicators = $point['indicators'] ?? [];
            $shows += (float) ($indicators['TOTAL_SHOWS'] ?? 0);
            $clicks += (float) ($indicators['TOTAL_CLICKS'] ?? 0);
        }

        if ($shows <= 0 && $clicks <= 0) {
            return null;
        }

        return [
            'shows' => $shows,
            'clicks' => $clicks,
            'ctr' => $this->computeCtr($clicks, $shows),
        ];
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchSqiHistory(
        string $accessToken,
        string $hostId,
        string $dateFrom,
        string $dateTo,
    ): array {
        return $this->fetchHistorySeries(
            $accessToken,
            $hostId,
            'sqi_history',
            $dateFrom,
            $dateTo,
            fn (array $point) => (float) ($point['value'] ?? 0),
            fn (string $date) => $this->formatDayLabel($date),
        );
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchIndexingHistory(
        string $accessToken,
        string $hostId,
        string $dateFrom,
        string $dateTo,
    ): array {
        return $this->fetchHistorySeries(
            $accessToken,
            $hostId,
            'indexing/history',
            $dateFrom,
            $dateTo,
            fn (array $point) => (float) ($point['searchable_pages_count'] ?? $point['value'] ?? 0),
            fn (string $date) => $this->formatDayLabel($date),
        );
    }

    /** @return list<array{label: string, value: float}> */
    private function fetchHistorySeries(
        string $accessToken,
        string $hostId,
        string $path,
        string $dateFrom,
        string $dateTo,
        callable $valueResolver,
        callable $labelResolver,
    ): array {
        $userId = $this->fetchUserId($accessToken);
        $response = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
        ])->get("https://api.webmaster.yandex.net/v4/user/{$userId}/hosts/{$hostId}/{$path}", [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Webmaster API error: '.$response->body());
        }

        return collect($response->json('history') ?? $response->json('points') ?? [])
            ->map(function (array $point) use ($valueResolver, $labelResolver) {
                $date = (string) ($point['date'] ?? $point['timestamp'] ?? '—');

                return [
                    'label' => $labelResolver($date),
                    'value' => $valueResolver($point),
                ];
            })
            ->values()
            ->all();
    }

    private function formatDayLabel(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 8, 2).'.'.substr($value, 5, 2);
        }

        return $value;
    }
}
