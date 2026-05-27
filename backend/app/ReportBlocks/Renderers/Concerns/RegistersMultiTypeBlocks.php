<?php

namespace App\ReportBlocks\Renderers\Concerns;

use App\Contracts\MultiTypeBlockRendererInterface;

trait RegistersMultiTypeBlocks
{
    /** @param  array<string, \App\Contracts\ReportBlockRendererInterface>  $renderers */
    protected function registerRenderer(array &$renderers, object $renderer): void
    {
        if ($renderer instanceof MultiTypeBlockRendererInterface) {
            foreach ($renderer->supportedTypes() as $type) {
                $renderers[$type] = $renderer;
            }

            return;
        }

        if (method_exists($renderer, 'type')) {
            $renderers[$renderer->type()] = $renderer;
        }
    }
}
