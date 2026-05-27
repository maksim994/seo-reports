<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\Enums\IntegrationProvider;
use App\Models\ProjectIntegration;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

abstract class AbstractIntegrationBlockRenderer implements ReportBlockRendererInterface
{
    abstract protected function blockTitle(): string;

    /** @return array{0: string, 1: ProjectIntegration}|null */
    protected function resolveBinding(ReportRenderContext $context, IntegrationProvider $provider): ?array
    {
        $binding = $context->bindingFor($provider->value);
        $token = $binding?->integration?->credentials['access_token'] ?? null;

        if (! $token || ! $binding) {
            return null;
        }

        return [$token, $binding];
    }

    protected function unavailable(string $message, ?string $title = null): ReportBlockResult
    {
        $title ??= $this->blockTitle();

        $html = View::make('reports.blocks.unavailable', [
            'title' => $title,
            'message' => $message,
        ])->render();

        return new ReportBlockResult($html, $title, success: false);
    }

    protected function periodDates(ReportRenderContext $context): array
    {
        return [
            'current' => [
                $context->job->period_start->format('Y-m-d'),
                $context->job->period_end->format('Y-m-d'),
            ],
            'previous' => ($context->job->compare_period_start && $context->job->compare_period_end)
                ? [
                    $context->job->compare_period_start->format('Y-m-d'),
                    $context->job->compare_period_end->format('Y-m-d'),
                ]
                : null,
        ];
    }
}
