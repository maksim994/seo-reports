<?php

namespace App\ReportBlocks\Renderers;

use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexWebmasterDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class WebmasterQueriesBlockRenderer extends AbstractIntegrationBlockRenderer
{
    public function __construct(private YandexWebmasterDataService $webmaster) {}

    public function type(): string
    {
        return 'webmaster_queries';
    }

    protected function blockTitle(): string
    {
        return 'Вебмастер: запросы';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $resolved = $this->resolveBinding($context, IntegrationProvider::YandexWebmaster);
        if (! $resolved) {
            return $this->unavailable('Яндекс.Вебмастер не привязан к проекту');
        }

        [$token, $binding] = $resolved;
        $hostId = $binding->external_resource_id;
        if (! $hostId) {
            return $this->unavailable('Сайт Вебмастера не указан');
        }

        try {
            $periods = $this->periodDates($context);
            $rows = $this->webmaster->fetchPopularQueries(
                $token,
                $hostId,
                $periods['current'][0],
                $periods['current'][1],
            );

            $html = View::make('reports.blocks.webmaster_queries', [
                'rows' => $rows,
                'hostLabel' => $binding->external_resource_label ?? $hostId,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные Вебмастера временно недоступны');
        }
    }
}
