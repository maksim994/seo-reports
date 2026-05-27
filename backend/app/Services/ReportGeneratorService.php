<?php

namespace App\Services;

use App\Enums\ReportJobStatus;
use App\Models\ReportFile;
use App\Models\ReportJob;
use App\ReportBlocks\ReportBlockRegistry;
use App\ReportBlocks\ReportRenderContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Throwable;

class ReportGeneratorService
{
    public function __construct(
        private ReportBlockRegistry $registry,
        private ReportBlockCatalog $catalog,
    ) {}

    public function generate(ReportJob $job): void
    {
        $job->update([
            'status' => ReportJobStatus::Fetching,
            'started_at' => now(),
            'error_message' => null,
        ]);

        try {
            $job->load([
                'project.projectIntegrations.integration',
                'template.blocks',
            ]);

            $bindings = $job->project->projectIntegrations->keyBy(
                fn ($binding) => $binding->integration->provider->value
            );

            $context = new ReportRenderContext(
                $job->project,
                $job->template,
                $job,
                $bindings,
                $this->catalog,
            );

            $job->update(['status' => ReportJobStatus::Rendering]);

            $sections = [];
            foreach ($job->template->blocks as $index => $block) {
                $result = $this->registry->render($block->block_type, $context, $block->settings);
                $sections[] = [
                    'anchor' => 'block-'.$index,
                    'html' => $result->html,
                ];
            }

            $html = View::make('reports.document', [
                'sections' => $sections,
                'job' => $job,
                'project' => $job->project,
                'template' => $job->template,
                'forPdf' => false,
            ])->render();

            $disk = (string) config('reports.storage_disk', 'local');
            $basePath = config('reports.storage_path_prefix', 'reports').'/'.$job->id;

            $this->storeFile($job, $disk, $basePath.'/report.html', 'html', $html);

            $pdfHtml = View::make('reports.document', [
                'sections' => $sections,
                'job' => $job,
                'project' => $job->project,
                'template' => $job->template,
                'forPdf' => true,
            ])->render();

            $pdfContent = Pdf::loadHTML($pdfHtml)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => false,
                    'isFontSubsettingEnabled' => false,
                ])
                ->output();
            $this->storeFile($job, $disk, $basePath.'/report.pdf', 'pdf', $pdfContent);

            $job->update([
                'status' => ReportJobStatus::Done,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => ReportJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function storeFile(ReportJob $job, string $disk, string $path, string $format, string $contents): void
    {
        Storage::disk($disk)->put($path, $contents);

        ReportFile::updateOrCreate(
            ['report_job_id' => $job->id, 'format' => $format],
            [
                'path' => $path,
                'size' => strlen($contents),
            ]
        );
    }
}
