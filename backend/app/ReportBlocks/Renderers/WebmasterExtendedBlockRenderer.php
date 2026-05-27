<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexWebmasterDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class WebmasterExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'webmaster_search_summary' => 'Вебмастер: эффективность в поиске',
        'webmaster_sqi_history' => 'Вебмастер: история ИКС',
        'webmaster_indexing_history' => 'Вебмастер: история индексации',
    ];

    public function __construct(private YandexWebmasterDataService $webmaster) {}

    public function type(): string
    {
        return 'webmaster_search_summary';
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
        $title = self::TITLES[$blockType] ?? 'Яндекс.Вебмастер';

        $resolved = $this->resolveBinding($context, IntegrationProvider::YandexWebmaster);
        if (! $resolved) {
            return $this->unavailable('Яндекс.Вебмастер не привязан к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $hostId = $binding->external_resource_id;
        if (! $hostId) {
            return $this->unavailable('Сайт Вебмастера не указан', $title);
        }

        try {
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            if ($blockType === 'webmaster_search_summary') {
                $summary = $this->webmaster->fetchSearchSummary($token, $hostId, $from, $to);
                $previous = null;
                if ($periods['previous']) {
                    $previous = $this->webmaster->fetchSearchSummary(
                        $token,
                        $hostId,
                        $periods['previous'][0],
                        $periods['previous'][1],
                    );
                }

                $html = View::make('reports.blocks.webmaster_search_summary', [
                    'title' => $title,
                    'hostLabel' => $binding->external_resource_label ?? $hostId,
                    'summary' => $summary,
                    'previous' => $previous,
                ])->render();
            } else {
                $series = match ($blockType) {
                    'webmaster_sqi_history' => $this->webmaster->fetchSqiHistory($token, $hostId, $from, $to),
                    default => $this->webmaster->fetchIndexingHistory($token, $hostId, $from, $to),
                };

                $html = View::make('reports.blocks.webmaster_history', [
                    'title' => $title,
                    'hostLabel' => $binding->external_resource_label ?? $hostId,
                    'series' => $series,
                    'valueLabel' => $blockType === 'webmaster_sqi_history' ? 'ИКС' : 'Страниц в поиске',
                ])->render();
            }

            return new ReportBlockResult($html, $title);
        } catch (Throwable) {
            return $this->unavailable('Данные Вебмастера временно недоступны', $title);
        }
    }

    protected function blockTitle(): string
    {
        return 'Яндекс.Вебмастер';
    }
}
