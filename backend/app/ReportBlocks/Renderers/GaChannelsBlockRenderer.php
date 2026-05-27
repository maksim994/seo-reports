<?php

namespace App\ReportBlocks\Renderers;

use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleAnalyticsDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class GaChannelsBlockRenderer extends AbstractIntegrationBlockRenderer
{
    public function __construct(private GoogleAnalyticsDataService $analytics) {}

    public function type(): string
    {
        return 'ga_channels';
    }

    protected function blockTitle(): string
    {
        return 'GA4: каналы';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $resolved = $this->resolveBinding($context, IntegrationProvider::GoogleAnalytics);
        if (! $resolved) {
            return $this->unavailable('Google Analytics не привязан к проекту');
        }

        [$token, $binding] = $resolved;
        $propertyId = $this->analytics->propertyIdFromBinding($binding);
        if (! $propertyId) {
            return $this->unavailable('Ресурс GA4 не указан');
        }

        try {
            $periods = $this->periodDates($context);
            $rows = $this->analytics->fetchChannels(
                $token,
                $propertyId,
                $periods['current'][0],
                $periods['current'][1],
            );

            $html = View::make('reports.blocks.ga_channels', [
                'rows' => $rows,
                'propertyLabel' => $binding->external_resource_label ?? $propertyId,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные GA4 временно недоступны');
        }
    }
}
