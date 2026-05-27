<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

class TextBlockRenderer implements ReportBlockRendererInterface
{
    public function type(): string
    {
        return 'text_block';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $html = View::make('reports.blocks.text_block', [
            'title' => $settings['title'] ?? 'Комментарии',
            'content' => $settings['content'] ?? 'Раздел для ручных комментариев специалиста.',
        ])->render();

        return new ReportBlockResult($html, $settings['title'] ?? 'Комментарии');
    }
}
