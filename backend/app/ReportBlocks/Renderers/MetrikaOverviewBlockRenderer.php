<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use App\Services\YandexMetrikaDataService;
use Illuminate\Support\Facades\View;
use Throwable;

class MetrikaOverviewBlockRenderer implements ReportBlockRendererInterface
{
    public function __construct(private YandexMetrikaDataService $metrika) {}

    public function type(): string
    {
        return 'metrika_overview';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $binding = $context->bindingFor(IntegrationProvider::YandexMetrika->value);
        $integration = $binding?->integration;
        $token = $integration?->credentials['access_token'] ?? null;
        $counterId = $this->metrika->counterIdFromBinding($binding);

        if (! $token || ! $counterId) {
            return $this->unavailable('Яндекс.Метрика не привязана к проекту');
        }

        try {
            $current = $this->metrika->fetchOverview(
                $token,
                $counterId,
                $context->job->period_start->format('Y-m-d'),
                $context->job->period_end->format('Y-m-d'),
            );

            $previous = null;
            if ($context->job->compare_period_start && $context->job->compare_period_end) {
                $previous = $this->metrika->fetchOverview(
                    $token,
                    $counterId,
                    $context->job->compare_period_start->format('Y-m-d'),
                    $context->job->compare_period_end->format('Y-m-d'),
                );
            }

            $html = View::make('reports.blocks.metrika_overview', [
                'current' => $current,
                'previous' => $previous,
                'counterLabel' => $binding->external_resource_label ?? $counterId,
            ])->render();

            return new ReportBlockResult($html, 'Метрика: обзор');
        } catch (Throwable $e) {
            return $this->unavailable('Данные Метрики временно недоступны');
        }
    }

    private function unavailable(string $message): ReportBlockResult
    {
        $html = View::make('reports.blocks.unavailable', [
            'title' => 'Метрика: обзор',
            'message' => $message,
        ])->render();

        return new ReportBlockResult($html, 'Метрика: обзор', success: false);
    }
}
