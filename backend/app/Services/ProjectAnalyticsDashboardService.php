<?php

namespace App\Services;

use App\Enums\IntegrationProvider;
use App\Models\Project;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\ReportBlocks\ReportBlockRegistry;
use App\ReportBlocks\ReportRenderContext;
use App\Services\ReportFetchCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ProjectAnalyticsDashboardService
{
    private const GRID_COLS = 12;

    /** @var array<string, list<string>> */
    private const DEFAULT_BLOCKS_BY_PROVIDER = [
        IntegrationProvider::YandexMetrika->value => ['metrika_overview', 'metrika_traffic_sources', 'metrika_search_engines_timeline', 'metrika_page_groups', 'metrika_page_group_conversions'],
        IntegrationProvider::GoogleAnalytics->value => ['ga_overview'],
        IntegrationProvider::GoogleSearchConsole->value => ['gsc_top_queries', 'gsc_performance'],
        IntegrationProvider::YandexWebmaster->value => ['webmaster_queries'],
        IntegrationProvider::Topvisor->value => ['positions_visibility'],
        IntegrationProvider::KeysSo->value => ['keys_so_site_queries', 'positions_visibility'],
        IntegrationProvider::YandexWordstat->value => ['wordstat_dynamics'],
    ];

    public function __construct(
        private ReportBlockRegistry $registry,
        private ReportBlockCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function showConfig(Project $project): array
    {
        $project->loadMissing(['projectIntegrations.integration']);

        $stored = $this->storedWidgets($project);
        $widgets = $stored !== [] ? $stored : $this->suggestedWidgets($project);
        $isSuggested = $stored === [];

        return [
            'widgets' => $widgets,
            'is_suggested' => $isSuggested,
            'catalog' => [
                'blocks' => $this->catalog->dashboardBlocks(),
                'categories' => $this->catalog->categories(),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return list<array<string, mixed>>
     */
    public function saveConfig(Project $project, array $widgets): array
    {
        $normalized = $this->normalizeWidgets($widgets);

        $settings = $project->settings ?? [];
        $settings['analytics_dashboard'] = ['widgets' => $normalized];
        $project->update(['settings' => $settings]);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return array<string, mixed>
     */
    public function fetchData(
        Project $project,
        array $widgets,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        bool $includeCompare = true,
    ): array {
        [$periodStart, $periodEnd, $compareStart, $compareEnd] = $this->resolvePeriods(
            $periodStart,
            $periodEnd,
            $includeCompare,
        );

        $widgets = $this->normalizeWidgets($widgets);
        if ($widgets === []) {
            return [
                'period' => [
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                ],
                'compare_period' => $includeCompare ? [
                    'start' => $compareStart->format('Y-m-d'),
                    'end' => $compareEnd->format('Y-m-d'),
                ] : null,
                'widgets' => [],
            ];
        }

        app()->instance(ReportFetchCache::class, new ReportFetchCache());
        View::share('forPdf', false);

        try {
            $context = $this->buildContext($project, $periodStart, $periodEnd, $compareStart, $compareEnd);

            $results = [];
            foreach ($widgets as $widget) {
                $blockType = (string) $widget['block_type'];
                $settings = is_array($widget['settings'] ?? null) ? $widget['settings'] : [];

                try {
                    $rendered = $this->registry->render($blockType, $context, $settings);
                    $prepared = $this->prepareWidgetHtml($rendered->html);
                    $results[] = [
                        'id' => (string) $widget['id'],
                        'block_type' => $blockType,
                        'title' => $rendered->title ?? $this->catalog->labelFor($blockType),
                        'chart_title' => $prepared['chart_title'],
                        'success' => $rendered->success,
                        'html' => $prepared['html'],
                        'error' => $rendered->success ? null : 'Данные временно недоступны',
                    ];
                } catch (\Throwable $e) {
                    $results[] = [
                        'id' => (string) $widget['id'],
                        'block_type' => $blockType,
                        'title' => $this->catalog->labelFor($blockType),
                        'success' => false,
                        'html' => null,
                        'error' => 'Не удалось загрузить виджет',
                    ];
                }
            }

            return [
                'period' => [
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                ],
                'compare_period' => $includeCompare ? [
                    'start' => $compareStart->format('Y-m-d'),
                    'end' => $compareEnd->format('Y-m-d'),
                ] : null,
                'widgets' => $results,
            ];
        } finally {
            app()->forgetInstance(ReportFetchCache::class);
        }
    }

    /** @return list<array<string, mixed>> */
    public function suggestedWidgets(Project $project): array
    {
        $project->loadMissing(['projectIntegrations.integration']);

        $providers = $project->projectIntegrations
            ->map(fn ($binding) => $binding->integration->provider->value)
            ->unique()
            ->values()
            ->all();

        $blockTypes = [];
        foreach ($providers as $provider) {
            foreach (self::DEFAULT_BLOCKS_BY_PROVIDER[$provider] ?? [] as $blockType) {
                if ($this->catalog->isDashboardEligible($blockType)) {
                    $blockTypes[] = $blockType;
                }
            }
        }

        if (
            in_array(IntegrationProvider::GoogleSearchConsole->value, $providers, true)
            && in_array(IntegrationProvider::YandexWebmaster->value, $providers, true)
            && $this->catalog->isDashboardEligible('search_clicks_compare')
        ) {
            $blockTypes[] = 'search_clicks_compare';
        }

        $blockTypes = array_values(array_unique($blockTypes));

        if ($blockTypes === []) {
            $blockTypes = ['metrika_overview', 'gsc_top_queries', 'positions_visibility'];
            $blockTypes = array_values(array_filter(
                $blockTypes,
                fn (string $type) => $this->catalog->isDashboardEligible($type),
            ));
        }

        return $this->layoutWidgets(array_map(
            fn (string $blockType) => [
                'id' => (string) Str::uuid(),
                'block_type' => $blockType,
                'settings' => $this->defaultWidgetSettings($blockType),
            ],
            array_slice($blockTypes, 0, 6),
        ));
    }

    /** @return list<array<string, mixed>> */
    private function storedWidgets(Project $project): array
    {
        $raw = $project->settings['analytics_dashboard']['widgets'] ?? [];

        return is_array($raw) ? $this->normalizeWidgets($raw) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return list<array<string, mixed>>
     */
    private function normalizeWidgets(array $widgets): array
    {
        $normalized = [];
        $y = 0;

        foreach ($widgets as $widget) {
            if (! is_array($widget)) {
                continue;
            }

            $blockType = (string) ($widget['block_type'] ?? '');
            if ($blockType === '' || ! $this->catalog->isDashboardEligible($blockType)) {
                continue;
            }

            $layout = is_array($widget['layout'] ?? null) ? $widget['layout'] : [];
            $w = max(1, min(self::GRID_COLS, (int) ($layout['w'] ?? 6)));
            $x = max(0, min(self::GRID_COLS - $w, (int) ($layout['x'] ?? 0)));
            $h = max(2, min(24, (int) ($layout['h'] ?? 4)));
            $y = max(0, (int) ($layout['y'] ?? 0));

            $normalized[] = [
                'id' => (string) ($widget['id'] ?? Str::uuid()),
                'block_type' => $blockType,
                'settings' => is_array($widget['settings'] ?? null) ? $widget['settings'] : [],
                'layout' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h],
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $widgets
     * @return list<array<string, mixed>>
     */
    private function layoutWidgets(array $widgets): array
    {
        $laid = [];
        $y = 0;

        foreach ($widgets as $index => $widget) {
            $w = 6;
            $x = $index % 2 === 0 ? 0 : 6;
            if ($index % 2 === 0 && $index > 0) {
                $y += 7;
            }

            $laid[] = array_merge($widget, [
                'layout' => ['x' => $x, 'y' => $y, 'w' => $w, 'h' => 7],
            ]);
        }

        return $laid;
    }

    /** @return array<string, mixed> */
    private function defaultWidgetSettings(string $blockType): array
    {
        return match ($blockType) {
            'metrika_search_engines_timeline' => ['chart_period' => '25_months'],
            'metrika_page_groups' => ['chart_period' => '12_months', 'traffic_scope' => 'organic'],
            'metrika_page_group_conversions' => ['chart_period' => '12_months', 'traffic_scope' => 'organic'],
            default => [],
        };
    }

    /** @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon} */
    private function resolvePeriods(?Carbon $periodStart, ?Carbon $periodEnd, bool $includeCompare): array
    {
        if ($periodStart === null || $periodEnd === null) {
            $periodEnd = now()->subMonth()->endOfMonth()->startOfDay();
            $periodStart = $periodEnd->copy()->startOfMonth();
        }

        $compareEnd = $periodStart->copy()->subDay();
        $daySpan = max(0, $periodStart->diffInDays($periodEnd));
        $compareStart = $compareEnd->copy()->subDays($daySpan);

        if (! $includeCompare) {
            $compareStart = $periodStart->copy();
            $compareEnd = $periodEnd->copy();
        }

        return [$periodStart, $periodEnd, $compareStart, $compareEnd];
    }

    private function buildContext(
        Project $project,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $compareStart,
        Carbon $compareEnd,
    ): ReportRenderContext {
        $project->loadMissing(['projectIntegrations.integration']);

        $bindings = $project->projectIntegrations->keyBy(
            fn ($binding) => $binding->integration->provider->value,
        );

        $template = new ReportTemplate([
            'user_id' => $project->user_id,
            'name' => 'Analytics Dashboard',
        ]);

        $job = new ReportJob([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'compare_period_start' => $compareStart,
            'compare_period_end' => $compareEnd,
        ]);

        return new ReportRenderContext(
            $project,
            $template,
            $job,
            $bindings,
            $this->catalog,
        );
    }

    /**
     * Убирает дубли с шапкой виджета: заголовок блока, счётчик, внутренние chart-title.
     *
     * @return array{html: string, chart_title: string|null}
     */
    private function prepareWidgetHtml(string $html): array
    {
        $html = preg_replace('/<h2[^>]*>.*?<\/h2>\s*/is', '', $html, 1) ?? $html;
        $html = preg_replace('/<p\s+class="muted"[^>]*>.*?<\/p>\s*/is', '', $html) ?? $html;

        $chartTitle = null;
        if (preg_match('/<div\s+class="chart-title"[^>]*>(.*?)<\/div>/is', $html, $match)) {
            $chartTitle = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($chartTitle === '') {
                $chartTitle = null;
            }
        }

        $html = preg_replace('/<div\s+class="chart-title"[^>]*>.*?<\/div>\s*/is', '', $html) ?? $html;

        return [
            'html' => trim($html),
            'chart_title' => $chartTitle,
        ];
    }
}
