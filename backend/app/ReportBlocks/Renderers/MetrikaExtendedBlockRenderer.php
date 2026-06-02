<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexMetrikaDataService;
use App\Support\MetrikaBlockSettings;
use Illuminate\Support\Facades\View;
use Throwable;

class MetrikaExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'metrika_devices' => 'Метрика: устройства',
        'metrika_geo' => 'Метрика: география',
        'metrika_daily_visits' => 'Метрика: динамика по дням',
        'metrika_monthly_visits' => 'Метрика: динамика по месяцам',
        'metrika_search_engines' => 'Метрика: поисковые системы',
        'metrika_search_engines_timeline' => 'Метрика: поисковые системы (динамика)',
        'metrika_organic_daily' => 'Метрика: поисковый трафик по дням',
        'metrika_landing_pages' => 'Метрика: посадочные страницы',
        'metrika_high_bounce' => 'Метрика: страницы с высоким отказом',
        'metrika_conversions_by_source' => 'Метрика: конверсии по каналам',
    ];

    public function __construct(private YandexMetrikaDataService $metrika) {}

    public function type(): string
    {
        return 'metrika_devices';
    }

    public function supportedTypes(): array
    {
        return array_keys(self::TITLES);
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        return $this->renderBlock($this->type(), $context, $settings);
    }

    public function renderBlock(string $blockType, ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $title = self::TITLES[$blockType] ?? 'Метрика';

        $resolved = $this->resolveBinding($context, IntegrationProvider::YandexMetrika);
        if (! $resolved) {
            return $this->unavailable('Яндекс.Метрика не привязана к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $counterId = $this->metrika->counterIdFromBinding($binding);
        if (! $counterId) {
            return $this->unavailable('Счётчик Метрики не указан', $title);
        }

        try {
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];
            $metrikaSettings = MetrikaBlockSettings::resolve($settings, $binding->config ?? []);
            $goalIds = $metrikaSettings['goal_ids'];

            $payload = match ($blockType) {
                'metrika_devices' => $this->payloadDevices($token, $counterId, $from, $to),
                'metrika_geo' => $this->payloadGeo($token, $counterId, $from, $to),
                'metrika_daily_visits' => $this->payloadDailyVisits($token, $counterId, $from, $to),
                'metrika_monthly_visits' => $this->payloadMonthlyVisits($token, $counterId, $to),
                'metrika_search_engines' => $this->payloadSearchEngines($token, $counterId, $from, $to),
                'metrika_search_engines_timeline' => $this->payloadSearchEnginesTimeline($token, $counterId, $to),
                'metrika_organic_daily' => $this->payloadOrganicDaily($token, $counterId, $from, $to, $periods['previous']),
                'metrika_landing_pages' => $this->payloadLandingPages($token, $counterId, $from, $to),
                'metrika_high_bounce' => $this->payloadHighBounce($token, $counterId, $from, $to),
                'metrika_conversions_by_source' => $this->payloadConversions($token, $counterId, $from, $to, $goalIds),
                default => ['rows' => []],
            };

            $html = View::make('reports.blocks.metrika_extended', array_merge($payload, [
                'title' => $title,
                'counterLabel' => $binding->external_resource_label ?? $counterId,
            ]))->render();

            return new ReportBlockResult($html, $title);
        } catch (Throwable) {
            return $this->unavailable('Данные Метрики временно недоступны', $title);
        }
    }

    protected function blockTitle(): string
    {
        return 'Метрика';
    }

    /** @return array<string, mixed> */
    private function payloadDevices(string $token, string $counterId, string $from, string $to): array
    {
        $rows = $this->metrika->fetchDevices($token, $counterId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Устройство', 'Визиты', 'Пользователи'],
            'columns' => ['label', 'visits', 'users'],
            'chartType' => 'donut',
            'valueKey' => 'visits',
            'donutOptions' => ['title' => 'Устройства', 'center_label' => 'Визиты'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadGeo(string $token, string $counterId, string $from, string $to): array
    {
        $rows = $this->metrika->fetchGeo($token, $counterId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Страна / регион', 'Визиты', 'Пользователи'],
            'columns' => ['label', 'visits', 'users'],
            'chartType' => 'donut',
            'valueKey' => 'visits',
            'donutOptions' => ['title' => 'География', 'center_label' => 'Визиты'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadDailyVisits(string $token, string $counterId, string $from, string $to): array
    {
        $lineSeries = $this->metrika->fetchVisitsTimeSeriesByTrafficSource($token, $counterId, $from, $to);

        return [
            'rows' => [],
            'lineSeries' => $lineSeries,
            'headers' => [],
            'columns' => [],
            'chartType' => 'line_timeseries_multi',
            'chartOptions' => ['max_points' => 62],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadMonthlyVisits(string $token, string $counterId, string $periodEnd): array
    {
        $lineSeries = $this->metrika->fetchMonthlyVisitsByTrafficSource($token, $counterId, $periodEnd);

        return [
            'rows' => [],
            'lineSeries' => $lineSeries,
            'headers' => [],
            'columns' => [],
            'chartType' => 'line_timeseries_multi',
            'chartOptions' => ['max_points' => 12],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadSearchEngines(string $token, string $counterId, string $from, string $to): array
    {
        $rows = $this->metrika->fetchSearchEngines($token, $counterId, $from, $to);

        return [
            'rows' => $rows,
            'headers' => ['Поисковая система', 'Визиты', 'Пользователи'],
            'columns' => ['label', 'visits', 'users'],
            'chartType' => 'donut',
            'valueKey' => 'visits',
            'donutOptions' => ['title' => 'Поисковые системы', 'center_label' => 'Визиты'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadSearchEnginesTimeline(string $token, string $counterId, string $periodEnd): array
    {
        $lineSeries = $this->metrika->fetchSearchEnginesMonthlyTimeline($token, $counterId, $periodEnd, 13);

        return [
            'rows' => [],
            'lineSeries' => $lineSeries,
            'headers' => [],
            'columns' => [],
            'chartType' => 'line_timeseries_multi',
            'chartOptions' => ['max_points' => 13],
        ];
    }

    /** @param  array{0: string, 1: string}|null  $previous */
    private function payloadOrganicDaily(
        string $token,
        string $counterId,
        string $from,
        string $to,
        ?array $previous,
    ): array {
        $current = $this->metrika->fetchOrganicDaily($token, $counterId, $from, $to);
        $previousSeries = null;
        if ($previous) {
            $previousSeries = $this->metrika->fetchOrganicDaily($token, $counterId, $previous[0], $previous[1]);
        }

        return [
            'rows' => $current,
            'previousSeries' => $previousSeries ?? [],
            'headers' => ['Дата', 'Визиты из поиска'],
            'columns' => ['label', 'value'],
            'chartType' => 'timeseries_overlay',
            'valueKey' => 'value',
            'chartOptions' => ['title' => 'Поисковый трафик по дням', 'max_points' => 62],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadLandingPages(string $token, string $counterId, string $from, string $to): array
    {
        $rows = $this->metrika->fetchLandingPagesByChannel($token, $counterId, $from, $to);

        return [
            'rows' => $rows,
            'tableMode' => 'by_channel',
            'channelColumns' => ['visits', 'users', 'bounce_rate'],
            'channelHeaders' => [
                'visits' => 'Визиты',
                'users' => 'Пользователи',
                'bounce_rate' => 'Отказы, %',
            ],
            'formatters' => ['bounce_rate' => 'percent'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadHighBounce(string $token, string $counterId, string $from, string $to): array
    {
        $rows = $this->metrika->fetchHighBouncePagesByChannel($token, $counterId, $from, $to);

        return [
            'rows' => $rows,
            'tableMode' => 'by_channel',
            'channelColumns' => ['visits', 'bounce_rate'],
            'channelHeaders' => [
                'visits' => 'Визиты',
                'bounce_rate' => 'Отказы, %',
            ],
            'formatters' => ['bounce_rate' => 'percent'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadConversions(string $token, string $counterId, string $from, string $to, ?array $goalIds): array
    {
        $rows = $this->metrika->fetchConversionsBySource($token, $counterId, $from, $to, $goalIds);

        return [
            'rows' => $rows,
            'headers' => ['Канал', 'Конверсии', 'Визиты', 'CR, %'],
            'columns' => ['label', 'conversions', 'visits', 'conversion_rate'],
            'chartType' => 'donut',
            'valueKey' => 'conversions',
            'donutOptions' => ['title' => 'Конверсии по каналам', 'center_label' => 'Конверсии'],
            'formatters' => ['conversion_rate' => 'percent'],
        ];
    }
}
