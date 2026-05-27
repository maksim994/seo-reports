<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\User;

class ReportTemplateService
{
    public function __construct(private ReportBlockCatalog $catalog) {}

    public function createDefaultForUser(User $user): ReportTemplate
    {
        $defaults = config('report_blocks.default_template');

        $template = $user->reportTemplates()->create([
            'name' => $defaults['name'],
            'description' => $defaults['description'] ?? null,
            'is_default' => true,
        ]);

        $this->syncBlocks($template, collect($this->catalog->defaultBlockTypes())
            ->map(fn (string $type) => ['block_type' => $type, 'settings' => null])
            ->all());

        return $template->load('blocks');
    }

    /** @param list<array{block_type: string, settings?: array|null}> $blocks */
    public function syncBlocks(ReportTemplate $template, array $blocks): void
    {
        $template->blocks()->delete();

        foreach ($blocks as $index => $block) {
            if (! $this->catalog->find($block['block_type'])) {
                continue;
            }

            $template->blocks()->create([
                'block_type' => $block['block_type'],
                'sort_order' => $index,
                'settings' => $block['settings'] ?? null,
            ]);
        }
    }
}
