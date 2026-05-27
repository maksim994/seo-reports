<?php

namespace App\ReportBlocks\Renderers;

use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleSearchConsoleDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class GscTopQueriesBlockRenderer extends AbstractIntegrationBlockRenderer
{
    public function __construct(private GoogleSearchConsoleDataService $searchConsole) {}

    public function type(): string
    {
        return 'gsc_top_queries';
    }

    protected function blockTitle(): string
    {
        return 'GSC: топ запросов';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $resolved = $this->resolveBinding($context, IntegrationProvider::GoogleSearchConsole);
        if (! $resolved) {
            return $this->unavailable('Google Search Console не привязан к проекту');
        }

        [$token, $binding] = $resolved;
        $siteUrl = $binding->external_resource_id;
        if (! $siteUrl) {
            return $this->unavailable('Сайт GSC не указан');
        }

        try {
            $periods = $this->periodDates($context);
            $rows = $this->searchConsole->fetchTopQueries(
                $token,
                $siteUrl,
                $periods['current'][0],
                $periods['current'][1],
            );

            $html = View::make('reports.blocks.gsc_top_queries', [
                'rows' => $rows,
                'siteLabel' => $binding->external_resource_label ?? $siteUrl,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные Search Console временно недоступны');
        }
    }
}
