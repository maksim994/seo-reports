<?php

namespace App\ReportBlocks;

use App\Contracts\MultiTypeBlockRendererInterface;
use App\ReportBlocks\Renderers\Concerns\RegistersMultiTypeBlocks;
use App\Services\ReportBlockCatalog;

class ReportBlockRegistry
{
    use RegistersMultiTypeBlocks;

    /** @var array<string, \App\Contracts\ReportBlockRendererInterface> */
    private array $renderers;

    public function __construct(
        private ReportBlockCatalog $catalog,
        Renderers\TitlePageBlockRenderer $titlePage,
        Renderers\TableOfContentsBlockRenderer $tableOfContents,
        Renderers\TextBlockRenderer $textBlock,
        Renderers\MetrikaOverviewBlockRenderer $metrikaOverview,
        Renderers\MetrikaTrafficSourcesBlockRenderer $metrikaTrafficSources,
        Renderers\MetrikaGoalsBlockRenderer $metrikaGoals,
        Renderers\MetrikaExtendedBlockRenderer $metrikaExtended,
        Renderers\GaOverviewBlockRenderer $gaOverview,
        Renderers\GaChannelsBlockRenderer $gaChannels,
        Renderers\GaExtendedBlockRenderer $gaExtended,
        Renderers\GscTopQueriesBlockRenderer $gscTopQueries,
        Renderers\GscExtendedBlockRenderer $gscExtended,
        Renderers\WebmasterQueriesBlockRenderer $webmasterQueries,
        Renderers\WebmasterExtendedBlockRenderer $webmasterExtended,
        Renderers\WordstatExtendedBlockRenderer $wordstatExtended,
        Renderers\SearchCompareBlockRenderer $searchCompare,
        Renderers\WorkPerformedBlockRenderer $workPerformed,
        Renderers\PositionsExtendedBlockRenderer $positionsExtended,
        Renderers\KeysSoExtendedBlockRenderer $keysSoExtended,
        Renderers\UnavailableBlockRenderer $unavailable,
    ) {
        $this->renderers = [];
        foreach ([
            $titlePage,
            $tableOfContents,
            $textBlock,
            $metrikaOverview,
            $metrikaTrafficSources,
            $metrikaGoals,
            $metrikaExtended,
            $gaOverview,
            $gaChannels,
            $gaExtended,
            $gscTopQueries,
            $gscExtended,
            $webmasterQueries,
            $webmasterExtended,
            $wordstatExtended,
            $searchCompare,
            $workPerformed,
            $positionsExtended,
            $keysSoExtended,
            $unavailable,
        ] as $renderer) {
            $this->registerRenderer($this->renderers, $renderer);
        }
    }

    public function render(string $blockType, ReportRenderContext $context, ?array $settings): ReportBlockResult
    {
        if (isset($this->renderers[$blockType])) {
            $renderer = $this->renderers[$blockType];
            if ($renderer instanceof MultiTypeBlockRendererInterface) {
                return $renderer->renderBlock($blockType, $context, $settings);
            }

            return $renderer->render($context, $settings);
        }

        return $this->renderers['unavailable']->render($context, [
            'block_type' => $blockType,
            'label' => $this->catalog->labelFor($blockType),
        ]);
    }
}
