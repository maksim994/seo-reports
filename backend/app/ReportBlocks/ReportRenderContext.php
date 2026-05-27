<?php

namespace App\ReportBlocks;

use App\Models\Project;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use App\Services\ReportBlockCatalog;
use Illuminate\Support\Collection;

class ReportRenderContext
{
    /** @param  Collection<string, \App\Models\ProjectIntegration>  $bindingsByProvider */
    public function __construct(
        public Project $project,
        public ReportTemplate $template,
        public ReportJob $job,
        public Collection $bindingsByProvider,
        public ReportBlockCatalog $catalog,
        /** @var list<array{title: string, anchor: string}> */
        public array $tocEntries = [],
    ) {}

    public function bindingFor(?string $provider): ?\App\Models\ProjectIntegration
    {
        if (! $provider) {
            return null;
        }

        return $this->bindingsByProvider->get($provider);
    }
}
