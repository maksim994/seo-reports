<?php

namespace App\Services;

use App\Models\ReportJob;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportPreviewService
{
    public function htmlContents(ReportJob $reportJob): ?string
    {
        $file = $reportJob->files()->where('format', 'html')->first();
        if (! $file) {
            return null;
        }

        $disk = (string) config('reports.storage_disk', 'local');
        $contents = Storage::disk($disk)->get($file->path);

        return $this->patchLegacyApexChartsUrls($contents);
    }

    public function download(ReportJob $reportJob, string $format): StreamedResponse
    {
        $file = $reportJob->files()->where('format', $format)->firstOrFail();
        $disk = (string) config('reports.storage_disk', 'local');
        $mime = $format === 'pdf' ? 'application/pdf' : 'text/html';
        $filename = sprintf('report-%d.%s', $reportJob->id, $format);

        return Storage::disk($disk)->download($file->path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    private function patchLegacyApexChartsUrls(string $html): string
    {
        $assetUrl = url('/api/vendor/apexcharts.min.js');

        foreach ((array) config('reports.apexcharts_legacy_cdn_urls', []) as $legacyUrl) {
            $html = str_replace($legacyUrl, $assetUrl, $html);
        }

        return $html;
    }
}
