<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\TopvisorDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class PositionsExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var array<string, string> */
    private const TITLES = [
        'positions_visibility' => 'Позиции: видимость',
        'positions_top_distribution' => 'Позиции: TOP-N',
        'positions_table' => 'Позиции: таблица ключей',
    ];

    public function __construct(private TopvisorDataService $topvisor) {}

    public function type(): string
    {
        return 'positions_visibility';
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
        $title = self::TITLES[$blockType] ?? 'Позиции';

        $resolved = $this->resolveBinding($context, IntegrationProvider::Topvisor);
        if (! $resolved) {
            return $this->unavailable('Topvisor не привязан к проекту', $title);
        }

        [$token, $binding] = $resolved;
        $credentials = $binding->integration?->credentials ?? [];
        $userId = (string) ($credentials['user_id'] ?? '');
        $apiKey = (string) ($credentials['api_key'] ?? $token);

        if ($userId === '' || $apiKey === '') {
            return $this->unavailable('API-ключ Topvisor не указан', $title);
        }

        $resourceId = $binding->external_resource_id;
        if (! $resourceId) {
            return $this->unavailable('Проект Topvisor не выбран', $title);
        }

        try {
            $parsed = $this->topvisor->parseBindingResourceId($resourceId);
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            $summary = $this->topvisor->fetchSummary(
                $userId,
                $apiKey,
                $parsed['project_id'],
                $parsed['region_index'],
                $from,
                $to,
            );

            $previousSummary = null;
            if ($periods['previous']) {
                $previousSummary = $this->topvisor->fetchSummary(
                    $userId,
                    $apiKey,
                    $parsed['project_id'],
                    $parsed['region_index'],
                    $periods['previous'][0],
                    $periods['previous'][1],
                );
            }

            $rows = $blockType === 'positions_table'
                ? $this->topvisor->fetchPositionsTable(
                    $userId,
                    $apiKey,
                    $parsed['project_id'],
                    $parsed['region_index'],
                    $from,
                    $to,
                )
                : [];

            $view = match ($blockType) {
                'positions_top_distribution' => 'reports.blocks.positions_top_distribution',
                'positions_table' => 'reports.blocks.positions_table',
                default => 'reports.blocks.positions_visibility',
            };

            $html = View::make($view, [
                'title' => $title,
                'resourceLabel' => $binding->external_resource_label ?? $resourceId,
                'summary' => $summary,
                'previousSummary' => $previousSummary,
                'rows' => $rows,
            ])->render();

            return new ReportBlockResult($html, $title);
        } catch (Throwable) {
            return $this->unavailable('Данные Topvisor временно недоступны', $title);
        }
    }

    protected function blockTitle(): string
    {
        return 'Позиции';
    }
}
