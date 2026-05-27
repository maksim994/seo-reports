<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\Project;
use App\Models\ReportJob;
use App\Models\ReportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = $request->user()
            ->reportJobs()
            ->with(['project:id,name,domain', 'template:id,name', 'files'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (ReportJob $job) => $this->serialize($job));

        return response()->json(['data' => $jobs]);
    }

    public function projectIndex(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $jobs = $project->reportJobs()
            ->where('user_id', $request->user()->id)
            ->with(['template:id,name', 'files'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ReportJob $job) => $this->serialize($job));

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'report_template_id' => ['required', 'integer', 'exists:report_templates,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'compare_period_start' => ['nullable', 'date', 'required_with:compare_period_end'],
            'compare_period_end' => ['nullable', 'date', 'required_with:compare_period_start'],
        ]);

        /** @var ReportTemplate $template */
        $template = ReportTemplate::query()->findOrFail($validated['report_template_id']);
        if ($template->user_id !== $request->user()->id) {
            abort(403);
        }

        $job = ReportJob::create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'report_template_id' => $template->id,
            'status' => ReportJobStatus::Queued,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'compare_period_start' => $validated['compare_period_start'] ?? null,
            'compare_period_end' => $validated['compare_period_end'] ?? null,
        ]);

        GenerateReportJob::dispatch($job);

        return response()->json([
            'data' => $this->serialize($job->load(['project:id,name,domain', 'template:id,name', 'files'])),
        ], 202);
    }

    public function show(Request $request, ReportJob $reportJob): JsonResponse
    {
        $this->authorize('view', $reportJob);

        return response()->json([
            'data' => $this->serialize($reportJob->load(['project:id,name,domain', 'template:id,name', 'files'])),
        ]);
    }

    public function preview(Request $request, ReportJob $reportJob)
    {
        $this->authorize('view', $reportJob);

        $file = $reportJob->files()->where('format', 'html')->first();
        if (! $file) {
            return response()->json(['message' => 'HTML-файл ещё не готов.'], 404);
        }

        $disk = (string) config('reports.storage_disk', 'local');
        $contents = Storage::disk($disk)->get($file->path);

        return response($contents, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function destroy(Request $request, ReportJob $reportJob): JsonResponse
    {
        $this->authorize('delete', $reportJob);

        $disk = (string) config('reports.storage_disk', 'local');
        $reportJob->load('files');

        foreach ($reportJob->files as $file) {
            Storage::disk($disk)->delete($file->path);
        }

        $reportJob->delete();

        return response()->json(null, 204);
    }

    public function download(Request $request, ReportJob $reportJob, string $format): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $reportJob);

        if (! in_array($format, ['html', 'pdf'], true)) {
            return response()->json(['message' => 'Unsupported format.'], 422);
        }

        $file = $reportJob->files()->where('format', $format)->first();
        if (! $file) {
            return response()->json(['message' => 'Файл ещё не готов.'], 404);
        }

        $disk = (string) config('reports.storage_disk', 'local');
        $mime = $format === 'pdf' ? 'application/pdf' : 'text/html';
        $filename = sprintf('report-%d.%s', $reportJob->id, $format);

        return Storage::disk($disk)->download($file->path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    private function serialize(ReportJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'period_start' => $job->period_start->format('Y-m-d'),
            'period_end' => $job->period_end->format('Y-m-d'),
            'compare_period_start' => $job->compare_period_start?->format('Y-m-d'),
            'compare_period_end' => $job->compare_period_end?->format('Y-m-d'),
            'error_message' => $job->error_message,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'created_at' => $job->created_at,
            'project' => $job->relationLoaded('project') ? [
                'id' => $job->project->id,
                'name' => $job->project->name,
                'domain' => $job->project->domain,
            ] : null,
            'template' => $job->relationLoaded('template') ? [
                'id' => $job->template->id,
                'name' => $job->template->name,
            ] : null,
            'files' => $job->relationLoaded('files')
                ? $job->files->map(fn ($f) => ['format' => $f->format, 'size' => $f->size])->values()
                : [],
        ];
    }
}
