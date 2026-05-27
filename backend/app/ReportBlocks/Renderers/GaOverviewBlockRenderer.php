<?php

namespace App\ReportBlocks\Renderers;

use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleAnalyticsDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class GaOverviewBlockRenderer extends AbstractIntegrationBlockRenderer
{
    public function __construct(private GoogleAnalyticsDataService $analytics) {}

    public function type(): string
    {
        return 'ga_overview';
    }

    protected function blockTitle(): string
    {
        return 'GA4: обзор';
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
            $current = $this->analytics->fetchOverview(
                $token,
                $propertyId,
                $periods['current'][0],
                $periods['current'][1],
            );

            $previous = null;
            if ($periods['previous']) {
                $previous = $this->analytics->fetchOverview(
                    $token,
                    $propertyId,
                    $periods['previous'][0],
                    $periods['previous'][1],
                );
            }

            $html = View::make('reports.blocks.ga_overview', [
                'current' => $current,
                'previous' => $previous,
                'propertyLabel' => $binding->external_resource_label ?? $propertyId,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные GA4 временно недоступны');
        }
    }
}
