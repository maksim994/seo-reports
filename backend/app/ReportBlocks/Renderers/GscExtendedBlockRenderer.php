<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\GoogleSearchConsoleDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class GscExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'gsc_performance' => 'GSC: эффективность в поиске',
        'gsc_top_pages' => 'GSC: топ посадочных страниц',
    ];

    public function __construct(private GoogleSearchConsoleDataService $searchConsole) {}

    public function type(): string
    {
        return 'gsc_performance';
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
        $title = self::TITLES[$blockType] ?? 'Search Console';

        $resolved = $this->resolveBinding($context, IntegrationProvider::GoogleSearchConsole);
        if (! $resolved) {
            return $this->unavailable('Google Search Console не привязан к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $siteUrl = $binding->external_resource_id;
        if (! $siteUrl) {
            return $this->unavailable('Сайт GSC не указан', $title);
        }

        try {
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            if ($blockType === 'gsc_performance') {
                $summary = $this->searchConsole->fetchPerformanceSummary($token, $siteUrl, $from, $to);
                $previous = null;
                if ($periods['previous']) {
                    $previous = $this->searchConsole->fetchPerformanceSummary(
                        $token,
                        $siteUrl,
                        $periods['previous'][0],
                        $periods['previous'][1],
                    );
                }

                $html = View::make('reports.blocks.gsc_performance', [
                    'title' => $title,
                    'siteLabel' => $binding->external_resource_label ?? $siteUrl,
                    'summary' => $summary,
                    'previous' => $previous,
                ])->render();
            } else {
                $rows = $this->searchConsole->fetchTopPages($token, $siteUrl, $from, $to);
                $html = View::make('reports.blocks.gsc_top_pages', [
                    'title' => $title,
                    'siteLabel' => $binding->external_resource_label ?? $siteUrl,
                    'rows' => $rows,
                ])->render();
            }

            return new ReportBlockResult($html, $title);
        } catch (Throwable) {
            return $this->unavailable('Данные Search Console временно недоступны', $title);
        }
    }

    protected function blockTitle(): string
    {
        return 'Search Console';
    }
}
