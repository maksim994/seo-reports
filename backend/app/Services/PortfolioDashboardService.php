<?php

namespace App\Services;

use App\DataTransferObjects\PositionBinding;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Enums\ReportJobStatus;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortfolioDashboardService
{
    /** @var list<IntegrationProvider> */
    private const POSITION_PROVIDERS = [
        IntegrationProvider::Topvisor,
        IntegrationProvider::KeysSo,
    ];

    public function __construct(
        private YandexMetrikaDataService $metrika,
        private GoogleSearchConsoleDataService $gsc,
        private YandexWebmasterDataService $webmaster,
        private PositionProviderRegistry $positions,
    ) {}

    /** @return array<string, mixed> */
    public function build(User $user, ?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        [$periodStart, $periodEnd, $compareStart, $compareEnd] = $this->resolvePeriods($periodStart, $periodEnd);

        $dateFrom = $periodStart->format('Y-m-d');
        $dateTo = $periodEnd->format('Y-m-d');
        $compareFrom = $compareStart->format('Y-m-d');
        $compareTo = $compareEnd->format('Y-m-d');

        $projects = $user->projects()
            ->with(['projectIntegrations.integration'])
            ->latest()
            ->get();

        $lastReports = $this->loadLastReports($user, $projects);

        return [
            'period' => [
                'start' => $dateFrom,
                'end' => $dateTo,
            ],
            'compare_period' => [
                'start' => $compareFrom,
                'end' => $compareTo,
            ],
            'projects' => $projects->map(function (Project $project) use (
                $dateFrom,
                $dateTo,
                $compareFrom,
                $compareTo,
                $lastReports,
            ) {
                return $this->buildProjectRow(
                    $project,
                    $dateFrom,
                    $dateTo,
                    $compareFrom,
                    $compareTo,
                    $lastReports->get($project->id),
                );
            })->values()->all(),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} */
    private function resolvePeriods(?Carbon $periodStart, ?Carbon $periodEnd): array
    {
        if ($periodStart === null || $periodEnd === null) {
            $periodEnd = now()->subMonth()->endOfMonth()->startOfDay();
            $periodStart = $periodEnd->copy()->startOfMonth();
        }

        $compareEnd = $periodStart->copy()->subDay();
        $compareStart = $compareEnd->copy()->startOfMonth();

        return [$periodStart, $periodEnd, $compareStart, $compareEnd];
    }

    /** @return Collection<int, \App\Models\ReportJob|null> */
    private function loadLastReports(User $user, Collection $projects): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }

        $jobs = $user->reportJobs()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('status', ReportJobStatus::Done)
            ->orderByDesc('finished_at')
            ->get()
            ->groupBy('project_id');

        return $projects->mapWithKeys(fn (Project $project) => [
            $project->id => $jobs->get($project->id)?->first(),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildProjectRow(
        Project $project,
        string $dateFrom,
        string $dateTo,
        string $compareFrom,
        string $compareTo,
        ?\App\Models\ReportJob $lastReport,
    ): array {
        $bindings = $project->projectIntegrations->keyBy(
            fn (ProjectIntegration $binding) => $binding->integration?->provider->value ?? '',
        );

        $errors = [];
        $integrations = [];

        foreach ($project->projectIntegrations as $binding) {
            $integration = $binding->integration;
            if (! $integration) {
                continue;
            }

            $integrations[] = $integration->provider->value;
            if ($integration->status !== IntegrationStatus::Active) {
                $errors[] = [
                    'provider' => $integration->provider->value,
                    'message' => $this->integrationStatusMessage($integration->status),
                ];
            }
        }

        return [
            'id' => $project->id,
            'name' => $project->name,
            'domain' => $project->domain,
            'has_analytics' => $project->has_analytics,
            'integrations' => array_values(array_unique($integrations)),
            'metrics' => [
                'metrika' => $this->fetchMetrikaMetrics($bindings, $dateFrom, $dateTo, $compareFrom, $compareTo, $errors),
                'search' => $this->fetchSearchMetrics($bindings, $dateFrom, $dateTo, $compareFrom, $compareTo, $errors),
                'positions' => $this->fetchPositionMetrics($bindings, $dateFrom, $dateTo, $errors),
            ],
            'last_report' => $lastReport ? [
                'id' => $lastReport->id,
                'period_start' => $lastReport->period_start->format('Y-m-d'),
                'period_end' => $lastReport->period_end->format('Y-m-d'),
                'finished_at' => $lastReport->finished_at,
            ] : null,
            'errors' => $errors,
        ];
    }

    /** @param  Collection<string, ProjectIntegration>  $bindings */
    private function fetchMetrikaMetrics(
        Collection $bindings,
        string $dateFrom,
        string $dateTo,
        string $compareFrom,
        string $compareTo,
        array &$errors,
    ): ?array {
        $binding = $bindings->get(IntegrationProvider::YandexMetrika->value);
        if (! $binding?->integration) {
            return null;
        }

        $token = (string) ($binding->integration->credentials['access_token'] ?? '');
        $counterId = $this->metrika->counterIdFromBinding($binding);
        if ($token === '' || ! $counterId) {
            return null;
        }

        try {
            $current = $this->metrika->fetchOverview($token, $counterId, $dateFrom, $dateTo);
            $previous = $this->metrika->fetchOverview($token, $counterId, $compareFrom, $compareTo);

            if ($current === null) {
                return null;
            }

            return [
                'visits' => $current['visits'],
                'users' => $current['users'],
                'visits_change_pct' => $this->changePercent($current['visits'], $previous['visits'] ?? null),
            ];
        } catch (Throwable $e) {
            Log::warning('Dashboard metrika fetch failed', ['message' => $e->getMessage()]);
            $errors[] = ['provider' => IntegrationProvider::YandexMetrika->value, 'message' => 'Метрика недоступна'];

            return null;
        }
    }

    /** @param  Collection<string, ProjectIntegration>  $bindings */
    private function fetchSearchMetrics(
        Collection $bindings,
        string $dateFrom,
        string $dateTo,
        string $compareFrom,
        string $compareTo,
        array &$errors,
    ): ?array {
        $gscBinding = $bindings->get(IntegrationProvider::GoogleSearchConsole->value);
        if ($gscBinding?->integration) {
            return $this->fetchGscMetrics($gscBinding, $dateFrom, $dateTo, $compareFrom, $compareTo, $errors);
        }

        $webmasterBinding = $bindings->get(IntegrationProvider::YandexWebmaster->value);
        if ($webmasterBinding?->integration) {
            return $this->fetchWebmasterMetrics($webmasterBinding, $dateFrom, $dateTo, $compareFrom, $compareTo, $errors);
        }

        return null;
    }

    private function fetchGscMetrics(
        ProjectIntegration $binding,
        string $dateFrom,
        string $dateTo,
        string $compareFrom,
        string $compareTo,
        array &$errors,
    ): ?array {
        $token = (string) ($binding->integration->credentials['access_token'] ?? '');
        $siteUrl = (string) $binding->external_resource_id;
        if ($token === '' || $siteUrl === '') {
            return null;
        }

        try {
            $current = $this->gsc->fetchPerformanceSummary($token, $siteUrl, $dateFrom, $dateTo);
            $previous = $this->gsc->fetchPerformanceSummary($token, $siteUrl, $compareFrom, $compareTo);

            if ($current === null) {
                return null;
            }

            return [
                'source' => 'google_search_console',
                'clicks' => $current['clicks'],
                'impressions' => $current['impressions'],
                'ctr' => $current['ctr'],
                'position' => $current['position'],
                'clicks_change_pct' => $this->changePercent($current['clicks'], $previous['clicks'] ?? null),
            ];
        } catch (Throwable $e) {
            Log::warning('Dashboard GSC fetch failed', ['message' => $e->getMessage()]);
            $errors[] = ['provider' => IntegrationProvider::GoogleSearchConsole->value, 'message' => 'GSC недоступен'];

            return null;
        }
    }

    private function fetchWebmasterMetrics(
        ProjectIntegration $binding,
        string $dateFrom,
        string $dateTo,
        string $compareFrom,
        string $compareTo,
        array &$errors,
    ): ?array {
        $token = (string) ($binding->integration->credentials['access_token'] ?? '');
        $hostId = (string) $binding->external_resource_id;
        if ($token === '' || $hostId === '') {
            return null;
        }

        try {
            $current = $this->webmaster->fetchSearchSummary($token, $hostId, $dateFrom, $dateTo);
            $previous = $this->webmaster->fetchSearchSummary($token, $hostId, $compareFrom, $compareTo);

            if ($current === null) {
                return null;
            }

            return [
                'source' => 'yandex_webmaster',
                'clicks' => $current['clicks'],
                'impressions' => $current['shows'],
                'ctr' => $current['ctr'],
                'clicks_change_pct' => $this->changePercent($current['clicks'], $previous['clicks'] ?? null),
            ];
        } catch (Throwable $e) {
            Log::warning('Dashboard Webmaster fetch failed', ['message' => $e->getMessage()]);
            $errors[] = ['provider' => IntegrationProvider::YandexWebmaster->value, 'message' => 'Вебмастер недоступен'];

            return null;
        }
    }

    /** @param  Collection<string, ProjectIntegration>  $bindings */
    private function fetchPositionMetrics(
        Collection $bindings,
        string $dateFrom,
        string $dateTo,
        array &$errors,
    ): ?array {
        foreach (self::POSITION_PROVIDERS as $providerEnum) {
            $binding = $bindings->get($providerEnum->value);
            if (! $binding?->integration) {
                continue;
            }

            $credentials = $binding->integration->credentials ?? [];
            $apiKey = (string) ($credentials['api_key'] ?? $credentials['api_token'] ?? $credentials['access_token'] ?? '');
            $userId = (string) ($credentials['user_id'] ?? '');

            if ($apiKey === '') {
                continue;
            }

            if ($providerEnum === IntegrationProvider::Topvisor && $userId === '') {
                continue;
            }

            if (! $binding->external_resource_id) {
                continue;
            }

            try {
                $positionBinding = new PositionBinding(
                    $userId,
                    $apiKey,
                    $binding->external_resource_id,
                    $binding->external_resource_label,
                    $binding->config,
                );

                $summary = $this->positions->get($providerEnum)->fetchSummary($positionBinding, $dateFrom, $dateTo);

                return [
                    'provider' => $providerEnum->value,
                    'visibility' => $summary['visibility'],
                    'visibility_dynamic' => $summary['visibility_dynamic'],
                    'top10' => $summary['tops']['top10'] ?? null,
                    'avg_position' => $summary['avg'],
                ];
            } catch (Throwable $e) {
                Log::warning('Dashboard positions fetch failed', [
                    'provider' => $providerEnum->value,
                    'message' => $e->getMessage(),
                ]);
                $errors[] = [
                    'provider' => $providerEnum->value,
                    'message' => $providerEnum->label().' недоступен',
                ];
            }
        }

        return null;
    }

    private function changePercent(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function integrationStatusMessage(IntegrationStatus $status): string
    {
        return match ($status) {
            IntegrationStatus::TokenExpired => 'Токен истёк — переподключите интеграцию',
            IntegrationStatus::Error => 'Ошибка интеграции',
            default => 'Интеграция неактивна',
        };
    }
}
