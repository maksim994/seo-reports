<?php

namespace App\Jobs;

use App\Models\ReportJob;
use App\Services\ReportGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReportJob $reportJob) {}

    public function handle(ReportGeneratorService $generator): void
    {
        try {
            $generator->generate($this->reportJob);
        } catch (Throwable) {
            // Status already updated in service.
        }
    }
}
