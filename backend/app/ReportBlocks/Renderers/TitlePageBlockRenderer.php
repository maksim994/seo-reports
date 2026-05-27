<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

class TitlePageBlockRenderer implements ReportBlockRendererInterface
{
    public function type(): string
    {
        return 'title_page';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $html = View::make('reports.blocks.title_page', [
            'project' => $context->project,
            'job' => $context->job,
            'template' => $context->template,
        ])->render();

        return new ReportBlockResult($html, 'Титульная страница');
    }
}
