<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

class UnavailableBlockRenderer implements ReportBlockRendererInterface
{
    public function type(): string
    {
        return 'unavailable';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $html = View::make('reports.blocks.unavailable', [
            'title' => $settings['label'] ?? $settings['block_type'] ?? 'Блок',
            'message' => 'Данные для этого блока пока недоступны или интеграция не подключена.',
        ])->render();

        return new ReportBlockResult(
            $html,
            $settings['label'] ?? null,
            success: false,
        );
    }
}
