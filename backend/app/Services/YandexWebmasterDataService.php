<?php

namespace App\Services;

use App\Support\ReportFetch;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class YandexWebmasterDataService
{
    /** @var list<string> */
    private const HOST_UNAVAILABLE_CODES = [
        'HOST_NOT_INDEXED',
        'HOST_NOT_LOADED',
        'HOST_NOT_VERIFIED',
    ];

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
            'date_from' => $this->formatApiDate($dateFrom),
            'date_to' => $this->formatApiDate($dateTo, endOfDay: true),
            'limit' => $limit,
        ]).'&query_indicator=TOTAL_SHOWS&query_indicator=TOTAL_CLICKS';
        $url = $this->hostUrl($userId, $hostId, 'search-queries/popular').'?'.$query;

        $payload = $this->request($accessToken, $url);

        return collect($payload['queries'] ?? [])
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
        return ReportFetch::remember(
            'webmaster.user.'.sha1($accessToken),
            function () use ($accessToken) {
                $payload = $this->request($accessToken, 'https://api.webmaster.yandex.net/v4/user');

                return (int) ($payload['user_id'] ?? 0);
            },
        );
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
            'date_from' => $this->formatApiDate($dateFrom),
            'date_to' => $this->formatApiDate($dateTo, endOfDay: true),
        ]).'&query_indicator=TOTAL_SHOWS&query_indicator=TOTAL_CLICKS';
        $url = $this->hostUrl($userId, $hostId, 'search-queries/all/history').'?'.$query;

        $payload = $this->request($accessToken, $url);
        [$shows, $clicks] = $this->sumQueryIndicators($payload);

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
        $userId = $this->fetchUserId($accessToken);
        $url = $this->hostUrl($userId, $hostId, 'sqi-history');
        $payload = $this->request($accessToken, $url, [
            'date_from' => $this->formatApiDate($dateFrom),
            'date_to' => $this->formatApiDate($dateTo, endOfDay: true),
        ]);

        return $this->mapPoints($payload['points'] ?? $payload['history'] ?? []);
    }

    /** @return list<array{label: string, value: float}> */
    public function fetchIndexingHistory(
        string $accessToken,
        string $hostId,
        string $dateFrom,
        string $dateTo,
    ): array {
        $userId = $this->fetchUserId($accessToken);
        $url = $this->hostUrl($userId, $hostId, 'indexing/history');
        $payload = $this->request($accessToken, $url, [
            'date_from' => $this->formatApiDate($dateFrom),
            'date_to' => $this->formatApiDate($dateTo, endOfDay: true),
        ]);

        $series = $payload['indicators']['HTTP_2XX'] ?? null;
        if (is_array($series) && $series !== []) {
            return $this->mapPoints($series);
        }

        return $this->mapIndicatorSeries($payload['indicators'] ?? []);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function sumQueryIndicators(array $payload): array
    {
        $shows = 0.0;
        $clicks = 0.0;

        if (isset($payload['indicators']) && is_array($payload['indicators'])) {
            foreach ($payload['indicators']['TOTAL_SHOWS'] ?? [] as $point) {
                $shows += (float) ($point['value'] ?? 0);
            }
            foreach ($payload['indicators']['TOTAL_CLICKS'] ?? [] as $point) {
                $clicks += (float) ($point['value'] ?? 0);
            }

            return [$shows, $clicks];
        }

        foreach ($payload['history'] ?? [] as $point) {
            $indicators = $point['indicators'] ?? [];
            $shows += (float) ($indicators['TOTAL_SHOWS'] ?? 0);
            $clicks += (float) ($indicators['TOTAL_CLICKS'] ?? 0);
        }

        return [$shows, $clicks];
    }

    /** @param  array<string, list<array<string, mixed>>>  $indicators */
    private function mapIndicatorSeries(array $indicators): array
    {
        $byDate = [];

        foreach ($indicators as $points) {
            if (! is_array($points)) {
                continue;
            }
            foreach ($points as $point) {
                $date = (string) ($point['date'] ?? '');
                if ($date === '') {
                    continue;
                }
                $byDate[$date] = ($byDate[$date] ?? 0) + (float) ($point['value'] ?? 0);
            }
        }

        ksort($byDate);

        return collect($byDate)
            ->map(fn (float $value, string $date) => [
                'label' => $this->formatDayLabel($date),
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /** @param  list<array<string, mixed>>  $points */
    private function mapPoints(array $points): array
    {
        return collect($points)
            ->map(function (array $point) {
                $date = (string) ($point['date'] ?? $point['timestamp'] ?? '—');

                return [
                    'label' => $this->formatDayLabel($date),
                    'value' => (float) ($point['value'] ?? $point['searchable_pages_count'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function hostUrl(int $userId, string $hostId, string $path): string
    {
        $encodedHost = rawurlencode($hostId);

        return "https://api.webmaster.yandex.net/v4/user/{$userId}/hosts/{$encodedHost}/{$path}";
    }

    /** @return array<string, mixed> */
    private function request(string $accessToken, string $url, array $query = []): array
    {
        $cacheKey = 'webmaster.req.'.sha1($accessToken.'|'.$url.'|'.json_encode($query));

        return ReportFetch::remember($cacheKey, function () use ($accessToken, $url, $query) {
            $response = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
            ])->get($url, $query);

            return $this->parseResponse($response);
        });
    }

    /** @return array<string, mixed> */
    private function parseResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $errorCode = (string) ($response->json('error_code') ?? '');
        if ($response->status() === 404 && in_array($errorCode, self::HOST_UNAVAILABLE_CODES, true)) {
            throw new RuntimeException('Webmaster host unavailable: '.$errorCode);
        }

        throw new RuntimeException('Webmaster API error: '.$response->body());
    }

    private function formatApiDate(string $date, bool $endOfDay = false): string
    {
        $time = $endOfDay ? '23:59:59' : '00:00:00';

        return $date.'T'.$time.'+03:00';
    }

    private function formatDayLabel(string $value): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
            return $matches[3].'.'.$matches[2];
        }

        return $value;
    }

    public static function friendlyErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'HOST_NOT_VERIFIED') => 'Сайт не подтверждён в Яндекс.Вебмастере. Подтвердите права на домен.',
            str_contains($message, 'HOST_NOT_INDEXED') => 'Сайт ещё не проиндексирован в Яндексе — данных по этому блоку пока нет.',
            str_contains($message, 'HOST_NOT_LOADED') => 'Данные сайта ещё не загружены в Вебмастер. Подождите обновления статистики.',
            default => 'Данные Вебмастера временно недоступны. Проверьте OAuth и привязку сайта.',
        };
    }
}
