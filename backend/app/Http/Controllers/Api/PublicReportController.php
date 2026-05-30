<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportPreviewService;
use App\Services\ReportShareService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicReportController extends Controller
{
    public function __construct(
        private ReportShareService $shareService,
        private ReportPreviewService $previewService,
    ) {}

    public function show(string $token): JsonResponse
    {
        $job = $this->shareService->resolveByToken($token);

        return response()->json([
            'data' => [
                'project_name' => $job->project?->name,
                'template_name' => $job->template?->name,
                'period_start' => $job->period_start->format('Y-m-d'),
                'period_end' => $job->period_end->format('Y-m-d'),
                'finished_at' => $job->finished_at,
                'formats' => $job->files->pluck('format')->values(),
            ],
        ]);
    }

    public function preview(string $token)
    {
        $job = $this->shareService->resolveByToken($token);
        $contents = $this->previewService->htmlContents($job);

        if ($contents === null) {
            return response()->json(['message' => 'HTML-файл ещё не готов.'], 404);
        }

        return response($contents, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function download(string $token, string $format): StreamedResponse|JsonResponse
    {
        if (! in_array($format, ['html', 'pdf'], true)) {
            return response()->json(['message' => 'Unsupported format.'], 422);
        }

        $job = $this->shareService->resolveByToken($token);

        if (! $job->files()->where('format', $format)->exists()) {
            return response()->json(['message' => 'Файл ещё не готов.'], 404);
        }

        return $this->previewService->download($job, $format);
    }
}
