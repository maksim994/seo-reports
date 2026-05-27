<?php

namespace App\Contracts;

use App\ReportBlocks\ReportBlockResult;
use App\ReportBlocks\ReportRenderContext;

interface MultiTypeBlockRendererInterface extends ReportBlockRendererInterface
{
    /** @return list<string> */
    public function supportedTypes(): array;

    public function renderBlock(string $blockType, ReportRenderContext $context, ?array $settings): ReportBlockResult;
}
