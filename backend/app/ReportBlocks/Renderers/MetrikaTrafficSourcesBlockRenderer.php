<?php

namespace App\ReportBlocks\Renderers;

use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexMetrikaDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class MetrikaTrafficSourcesBlockRenderer extends AbstractIntegrationBlockRenderer
{
    public function __construct(private YandexMetrikaDataService $metrika) {}

    public function type(): string
    {
        return 'metrika_traffic_sources';
    }

    protected function blockTitle(): string
    {
        return 'Метрика: источники трафика';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $resolved = $this->resolveBinding($context, IntegrationProvider::YandexMetrika);
        if (! $resolved) {
            return $this->unavailable('Яндекс.Метрика не привязана к проекту');
        }

        [$token, $binding] = $resolved;
        $counterId = $this->metrika->counterIdFromBinding($binding);
        if (! $counterId) {
            return $this->unavailable('Счётчик Метрики не указан');
        }

        try {
            $periods = $this->periodDates($context);
            $rows = $this->metrika->fetchTrafficSources(
                $token,
                $counterId,
                $periods['current'][0],
                $periods['current'][1],
            );

            $html = View::make('reports.blocks.metrika_traffic_sources', [
                'rows' => $rows,
                'counterLabel' => $binding->external_resource_label ?? $counterId,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные Метрики временно недоступны');
        }
    }
}
