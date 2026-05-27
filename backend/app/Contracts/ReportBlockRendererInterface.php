<?php

namespace App\Contracts;

use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;

interface ReportBlockRendererInterface
{
    public function type(): string;

    public function render(ReportRenderContext $context, ?array $settings): ReportBlockResult;
}
