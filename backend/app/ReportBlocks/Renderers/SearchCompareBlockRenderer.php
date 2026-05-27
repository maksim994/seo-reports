<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleSearchConsoleDataService;
use App\Services\YandexWebmasterDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class SearchCompareBlockRenderer extends AbstractIntegrationBlockRenderer implements ReportBlockRendererInterface
{
    public function __construct(
        private GoogleSearchConsoleDataService $searchConsole,
        private YandexWebmasterDataService $webmaster,
    ) {}

    public function type(): string
    {
        return 'search_clicks_compare';
    }

    protected function blockTitle(): string
    {
        return 'Сравнение: Яндекс vs Google';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $gscBinding = $context->bindingFor(IntegrationProvider::GoogleSearchConsole->value);
        $wmBinding = $context->bindingFor(IntegrationProvider::YandexWebmaster->value);
        $gscToken = $gscBinding?->integration?->credentials['access_token'] ?? null;
        $wmToken = $wmBinding?->integration?->credentials['access_token'] ?? null;

        if (! $gscToken || ! $wmToken || ! $gscBinding || ! $wmBinding) {
            return $this->unavailable('Нужны привязки Search Console и Яндекс.Вебмастера');
        }

        $siteUrl = $gscBinding->external_resource_id;
        $hostId = $wmBinding->external_resource_id;
        if (! $siteUrl || ! $hostId) {
            return $this->unavailable('Не указаны сайты для сравнения');
        }

        try {
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            $google = $this->searchConsole->fetchPerformanceSummary($gscToken, $siteUrl, $from, $to);
            $yandex = $this->webmaster->fetchSearchSummary($wmToken, $hostId, $from, $to);

            $html = View::make('reports.blocks.search_clicks_compare', [
                'google' => $google,
                'yandex' => $yandex,
                'siteLabel' => $gscBinding->external_resource_label ?? $siteUrl,
                'hostLabel' => $wmBinding->external_resource_label ?? $hostId,
            ])->render();

            return new ReportBlockResult($html, $this->blockTitle());
        } catch (Throwable) {
            return $this->unavailable('Данные для сравнения временно недоступны');
        }
    }
}
