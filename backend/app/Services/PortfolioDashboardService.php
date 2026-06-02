<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Enums\ReportJobStatus;
use App\Models\Project;
use App\Models\ProjectIntegration;
use App\Models\User;
use App\Models\WorkItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class PortfolioDashboardService
{
    public function __construct(
        private YandexMetrikaDataService $metrika,
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
        $workItemCounts = $this->loadWorkItemCounts(
            $projects->pluck('id')->all(),
            $dateFrom,
            $dateTo,
        );

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
                $workItemCounts,
            ) {
                return $this->buildProjectRow(
                    $project,
                    $dateFrom,
                    $dateTo,
                    $compareFrom,
                    $compareTo,
                    $lastReports->get($project->id),
                    (int) ($workItemCounts[$project->id] ?? 0),
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

    /**
     * @param  list<int>  $projectIds
     * @return array<int, int>
     */
    private function loadWorkItemCounts(array $projectIds, string $dateFrom, string $dateTo): array
    {
        if ($projectIds === []) {
            return [];
        }

        return WorkItem::query()
            ->whereIn('project_id', $projectIds)
            ->whereBetween('work_date', [$dateFrom, $dateTo])
            ->groupBy('project_id')
            ->selectRaw('project_id, count(*) as aggregate')
            ->pluck('aggregate', 'project_id')
            ->map(fn ($count) => (int) $count)
            ->all();
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
        int $workItemsCount,
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
            ],
            'summary' => [
                'work_items_count' => $workItemsCount,
                'integrations_count' => count($integrations),
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
                'bounce_rate' => $current['bounce_rate'],
                'visits_change_pct' => $this->changePercent($current['visits'], $previous['visits'] ?? null),
                'users_change_pct' => $this->changePercent($current['users'], $previous['users'] ?? null),
            ];
        } catch (Throwable $e) {
            Log::warning('Dashboard metrika fetch failed', ['message' => $e->getMessage()]);
            $errors[] = ['provider' => IntegrationProvider::YandexMetrika->value, 'message' => 'Метрика недоступна'];

            return null;
        }
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
