<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\DataTransferObjects\PositionBinding;
use App\Enums\IntegrationProvider;
use App\Models\ProjectIntegration;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\PositionProviderRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Throwable;

class PositionsExtendedBlockRenderer extends AbstractIntegrationBlockRenderer implements MultiTypeBlockRendererInterface
{
    /** @var list<IntegrationProvider> */
    private const POSITION_PROVIDERS = [
        IntegrationProvider::Topvisor,
        IntegrationProvider::KeysSo,
    ];

    /** @var array<string, string> */
    private const TITLES = [
        'positions_visibility' => 'Позиции: видимость',
        'positions_top_distribution' => 'Позиции: TOP-N',
        'positions_table' => 'Позиции: таблица ключей',
    ];

    public function __construct(private PositionProviderRegistry $positions) {}

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

        $resolved = $this->resolvePositionContext($context);
        if (! $resolved) {
            return $this->unavailable('Не подключён провайдер позиций (Topvisor или Keys.so)', $title);
        }

        [$providerEnum, $binding] = $resolved;
        $credentials = $binding->integration?->credentials ?? [];
        $apiKey = (string) ($credentials['api_key'] ?? $credentials['api_token'] ?? $credentials['access_token'] ?? '');
        $userId = (string) ($credentials['user_id'] ?? '');

        if ($apiKey === '') {
            return $this->unavailable('API-ключ провайдера позиций не указан', $title);
        }

        if ($providerEnum === IntegrationProvider::Topvisor && $userId === '') {
            return $this->unavailable('User ID Topvisor не указан', $title);
        }

        $resourceId = $binding->external_resource_id;
        if (! $resourceId) {
            return $this->unavailable('Проект мониторинга не выбран', $title);
        }

        try {
            $positionBinding = new PositionBinding(
                $userId,
                $apiKey,
                $resourceId,
                $binding->external_resource_label,
                $binding->config,
            );

            $provider = $this->positions->get($providerEnum);
            $periods = $this->periodDates($context);
            [$from, $to] = $periods['current'];

            $summary = $provider->fetchSummary($positionBinding, $from, $to);

            $previousSummary = null;
            if ($periods['previous']) {
                $previousSummary = $provider->fetchSummary(
                    $positionBinding,
                    $periods['previous'][0],
                    $periods['previous'][1],
                );
            }

            $rows = $blockType === 'positions_table'
                ? $provider->fetchPositionsTable(
                    $positionBinding,
                    $from,
                    $to,
                    max(1, min(200, (int) ($settings['limit'] ?? 50))),
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
        } catch (Throwable $e) {
            Log::warning('Report block data fetch failed', [
                'block' => $title,
                'provider' => $providerEnum->value,
                'message' => $e->getMessage(),
            ]);

            $providerLabel = $providerEnum->label();
            $message = $this->positionErrorMessage($providerLabel, $e->getMessage());

            return $this->unavailable($message, $title);
        }
    }

    private function positionErrorMessage(string $providerLabel, string $error): string
    {
        if (str_contains($error, 'has no tracked regions')) {
            return "У выбранного проекта {$providerLabel} нет регионов мониторинга. Откройте «Источники» и выберите другой проект.";
        }

        if (str_contains($error, 'not found')) {
            return "Проект {$providerLabel} не найден. Проверьте привязку в «Источники».";
        }

        return "Данные {$providerLabel} временно недоступны";
    }

    /** @return array{0: IntegrationProvider, 1: ProjectIntegration}|null */
    private function resolvePositionContext(ReportRenderContext $context): ?array
    {
        foreach (self::POSITION_PROVIDERS as $provider) {
            $resolved = $this->resolveBinding($context, $provider);
            if ($resolved) {
                return [$provider, $resolved[1]];
            }
        }

        return null;
    }

    protected function blockTitle(): string
    {
        return 'Позиции';
    }
}
