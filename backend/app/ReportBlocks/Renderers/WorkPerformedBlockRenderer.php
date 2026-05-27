<?php

namespace App\ReportBlocks\Renderers;

use App\Contracts\ReportBlockRendererInterface;
use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;
use Illuminate\Support\Facades\View;

class WorkPerformedBlockRenderer implements ReportBlockRendererInterface
{
    public function type(): string
    {
        return 'work_performed';
    }

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        $items = $context->project->workItems()
            ->whereDate('work_date', '>=', $context->job->period_start)
            ->whereDate('work_date', '<=', $context->job->period_end)
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        $html = View::make('reports.blocks.work_performed', [
            'items' => $items,
            'periodLabel' => $context->job->period_start->format('d.m.Y')
                .' — '.$context->job->period_end->format('d.m.Y'),
        ])->render();

        return new ReportBlockResult($html, 'Проделанная работа');
    }
}
