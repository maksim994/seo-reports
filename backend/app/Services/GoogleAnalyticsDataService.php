<?php

namespace App\Services;

use App\Support\ReportFetch;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAnalyticsDataService
{
    public function propertyIdFromBinding(?\App\Models\ProjectIntegration $binding): ?string
    {
        if (! $binding?->external_resource_id) {
            return null;
        }

        $id = $binding->external_resource_id;

        return str_starts_with($id, 'properties/')
            ? substr($id, strlen('properties/'))
            : $id;
    }

    /** @return array{sessions: float, users: float, engagement_rate: float}|null */
    public function fetchOverview(string $accessToken, string $propertyId, string $dateFrom, string $dateTo): ?array
    {
        $response = $this->runReport($accessToken, $propertyId, $dateFrom, $dateTo, [
            ['name' => 'sessions'],
            ['name' => 'totalUsers'],
            ['name' => 'engagementRate'],
        ]);

        $row = $response['rows'][0]['metricValues'] ?? null;
        if (! $row) {
            return null;
        }

        return [
            'sessions' => (float) ($row[0]['value'] ?? 0),
            'users' => (float) ($row[1]['value'] ?? 0),
            'engagement_rate' => (float) ($row[2]['value'] ?? 0) * 100,
        ];
    }

    /** @return list<array{channel: string, sessions: float, users: float}> */
    public function fetchChannels(
        string $accessToken,
        string $propertyId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        $response = $this->runReport($accessToken, $propertyId, $dateFrom, $dateTo, [
            ['name' => 'sessions'],
            ['name' => 'totalUsers'],
        ], [
            ['name' => 'sessionDefaultChannelGroup'],
        ], $limit);

        return collect($response['rows'] ?? [])
            ->map(function (array $row) {
                return [
                    'channel' => $row['dimensionValues'][0]['value'] ?? '—',
                    'sessions' => (float) ($row['metricValues'][0]['value'] ?? 0),
                    'users' => (float) ($row['metricValues'][1]['value'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array{label: string, sessions: float, users: float}> */
    public function fetchDevices(
        string $accessToken,
        string $propertyId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10,
    ): array {
        $response = $this->runReport($accessToken, $propertyId, $dateFrom, $dateTo, [
            ['name' => 'sessions'],
            ['name' => 'totalUsers'],
        ], [
            ['name' => 'deviceCategory'],
        ], $limit);

        return collect($response['rows'] ?? [])
            ->map(fn (array $row) => [
                'label' => $this->translateDevice($row['dimensionValues'][0]['value'] ?? '—'),
                'sessions' => (float) ($row['metricValues'][0]['value'] ?? 0),
                'users' => (float) ($row['metricValues'][1]['value'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, sessions: float, users: float}> */
    public function fetchGeo(
        string $accessToken,
        string $propertyId,
        string $dateFrom,
        string $dateTo,
        int $limit = 15,
    ): array {
        $response = $this->runReport($accessToken, $propertyId, $dateFrom, $dateTo, [
            ['name' => 'sessions'],
            ['name' => 'totalUsers'],
        ], [
            ['name' => 'country'],
        ], $limit);

        return collect($response['rows'] ?? [])
            ->map(fn (array $row) => [
                'label' => $row['dimensionValues'][0]['value'] ?? '—',
                'sessions' => (float) ($row['metricValues'][0]['value'] ?? 0),
                'users' => (float) ($row['metricValues'][1]['value'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{label: string, sessions: float, users: float, engagement_rate: float}> */
    public function fetchLandingPages(
        string $accessToken,
        string $propertyId,
        string $dateFrom,
        string $dateTo,
        int $limit = 15,
    ): array {
        $response = $this->runReport($accessToken, $propertyId, $dateFrom, $dateTo, [
            ['name' => 'sessions'],
            ['name' => 'totalUsers'],
            ['name' => 'engagementRate'],
        ], [
            ['name' => 'landingPage'],
        ], $limit);

        return collect($response['rows'] ?? [])
            ->map(fn (array $row) => [
                'label' => $this->truncatePath($row['dimensionValues'][0]['value'] ?? '—'),
                'sessions' => (float) ($row['metricValues'][0]['value'] ?? 0),
                'users' => (float) ($row['metricValues'][1]['value'] ?? 0),
                'engagement_rate' => (float) ($row['metricValues'][2]['value'] ?? 0) * 100,
            ])
            ->values()
            ->all();
    }

    private function translateDevice(string $value): string
    {
        return match ($value) {
            'desktop' => 'Компьютеры',
            'mobile' => 'Смартфоны',
            'tablet' => 'Планшеты',
            'smart tv' => 'Smart TV',
            default => $value,
        };
    }

    private function truncatePath(string $path): string
    {
        if (mb_strlen($path) <= 72) {
            return $path;
        }

        return mb_substr($path, 0, 71).'…';
    }

    /** @param list<array{name: string}> $metrics @param list<array{name: string}> $dimensions */
    private function runReport(
        string $accessToken,
        string $propertyId,
        string $dateFrom,
        string $dateTo,
        array $metrics,
        array $dimensions = [],
        ?int $limit = null,
    ): array {
        $body = [
            'dateRanges' => [['startDate' => $dateFrom, 'endDate' => $dateTo]],
            'metrics' => $metrics,
        ];

        if ($dimensions) {
            $body['dimensions'] = $dimensions;
        }
        if ($limit) {
            $body['limit'] = $limit;
        }

        $response = ReportFetch::remember(
            'ga.report.'.sha1($accessToken.'|'.$propertyId.'|'.json_encode($body)),
            function () use ($accessToken, $propertyId, $body) {
                $response = Http::withToken($accessToken)
                    ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", $body);

                if (! $response->successful()) {
                    throw new RuntimeException('GA4 API error: '.$response->body());
                }

                return $response->json();
            },
        );

        return $response;
    }
}
