<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

class TableOfContentsBlockRenderer implements ReportBlockRendererInterface
{
    public function type(): string
    {
        return 'table_of_contents';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $entries = [];
        foreach ($context->template->blocks as $index => $block) {
            if ($block->block_type === 'table_of_contents') {
                continue;
            }
            $entries[] = [
                'title' => $context->catalog->labelFor($block->block_type),
                'anchor' => 'block-'.$index,
            ];
        }

        $html = View::make('reports.blocks.table_of_contents', [
            'entries' => $entries,
        ])->render();

        return new ReportBlockResult($html, 'Содержание');
    }
}
