<?php

namespace App\Services;

class ReportBlockCatalog
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return config('report_blocks.blocks', []);
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return config('report_blocks.categories', []);
    }

    public function find(string $blockType): ?array
    {
        foreach ($this->all() as $block) {
            if ($block['block_type'] === $blockType) {
                return $block;
            }
        }

        return null;
    }

    public function labelFor(string $blockType): string
    {
        return $this->find($blockType)['label'] ?? $blockType;
    }

    /** @return list<string> */
    public function defaultBlockTypes(): array
    {
        return config('report_blocks.default_template.blocks', []);
    }
}
