<?php

namespace App\Http\Controllers\Api;

use App\Enums\TechnicalAuditJobStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RunTechnicalAuditJob;
use App\Models\Project;
use App\Models\TechnicalAuditJob;
use App\Services\CursorAgentClient;
use App\Services\TechnicalAuditDeliveryService;
use App\Services\TechnicalAuditRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TechnicalAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = $request->user()
            ->technicalAuditJobs()
            ->with(['project:id,name,domain', 'files'])
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (TechnicalAuditJob $job) => $this->serialize($job));

        return response()->json(['data' => $jobs]);
    }

    public function projectIndex(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $jobs = $project->technicalAuditJobs()
            ->where('user_id', $request->user()->id)
            ->with(['files'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (TechnicalAuditJob $job) => $this->serialize($job));

        return response()->json(['data' => $jobs]);
    }

    public function store(Request $request, Project $project, CursorAgentClient $cursor): JsonResponse
    {
        $this->authorize('view', $project);

        if (! $cursor->isConfigured()) {
            return response()->json([
                'message' => 'Технические аудиты недоступны: не настроен CURSOR_API_KEY.',
            ], 503);
        }

        $validated = $request->validate([
            'site_url' => ['required', 'url', 'max:2048'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'sample_urls' => ['nullable', 'array', 'max:10'],
            'sample_urls.*' => ['url', 'max:2048'],
            'crawl_depth' => ['nullable', 'in:light,sitemap'],
            'lang' => ['nullable', 'in:ru,en'],
        ]);

        $siteUrl = $validated['site_url'];
        if (! str_ends_with($siteUrl, '/')) {
            $siteUrl .= '/';
        }

        $job = TechnicalAuditJob::create([
            'user_id' => $request->user()->id,
            'project_id' => $project->id,
            'status' => TechnicalAuditJobStatus::Queued,
            'site_url' => $siteUrl,
            'site_name' => $validated['site_name'] ?? $project->name,
            'sample_urls' => $validated['sample_urls'] ?? [],
            'crawl_depth' => $validated['crawl_depth'] ?? 'light',
            'lang' => $validated['lang'] ?? 'ru',
            'activity_log' => [[
                'at' => now()->toIso8601String(),
                'level' => 'info',
                'message' => 'Задача создана. Ожидаем запуск worker.',
                'context' => null,
            ]],
        ]);

        RunTechnicalAuditJob::dispatch($job);

        $warnings = [];
        if (! $job->webhookReachable()) {
            $warnings[] = 'Webhook недоступен из Cloud Agent (APP_URL=localhost). JSON будет получен через Cursor API после завершения аудита.';
        }

        return response()->json([
            'data' => $this->serialize($job->load(['project:id,name,domain', 'files'])),
            'warnings' => $warnings,
        ], 202);
    }

    public function sync(
        Request $request,
        TechnicalAuditJob $technicalAuditJob,
        TechnicalAuditRecoveryService $recovery,
    ): JsonResponse {
        $this->authorize('view', $technicalAuditJob);

        if ($technicalAuditJob->files()->exists()) {
            return response()->json([
                'data' => $this->serialize($technicalAuditJob->load(['project:id,name,domain', 'files'])),
                'message' => 'Отчёт уже сохранён.',
            ]);
        }

        try {
            $recovery->startJsonRecovery($technicalAuditJob);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($technicalAuditJob->fresh()->load(['project:id,name,domain', 'files'])),
            'message' => 'Запущено получение JSON от Cloud Agent.',
        ], 202);
    }

    public function import(
        Request $request,
        TechnicalAuditJob $technicalAuditJob,
        TechnicalAuditRecoveryService $recovery,
        TechnicalAuditDeliveryService $delivery,
    ): JsonResponse {
        $this->authorize('view', $technicalAuditJob);

        if ($request->hasFile('file')) {
            $contents = $request->file('file')?->get();
            $payload = json_decode((string) $contents, true);
        } else {
            $payload = $request->json()->all();
        }

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Нужен JSON-файл или JSON body.'], 422);
        }

        try {
            $recovery->importPayload($technicalAuditJob, $payload, $delivery);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serialize($technicalAuditJob->fresh()->load(['project:id,name,domain', 'files'])),
            'message' => 'Отчёт импортирован.',
        ]);
    }

    public function show(Request $request, TechnicalAuditJob $technicalAuditJob): JsonResponse
    {
        $this->authorize('view', $technicalAuditJob);

        return response()->json([
            'data' => $this->serialize($technicalAuditJob->load(['project:id,name,domain', 'files'])),
        ]);
    }

    public function destroy(Request $request, TechnicalAuditJob $technicalAuditJob): JsonResponse
    {
        $this->authorize('delete', $technicalAuditJob);

        $disk = (string) config('technical_audit.storage_disk', 'local');
        $technicalAuditJob->load('files');

        foreach ($technicalAuditJob->files as $file) {
            Storage::disk($disk)->delete($file->path);
        }

        $technicalAuditJob->delete();

        return response()->json(null, 204);
    }

    public function download(Request $request, TechnicalAuditJob $technicalAuditJob, string $format): StreamedResponse|JsonResponse
    {
        $this->authorize('view', $technicalAuditJob);

        if (! in_array($format, ['json', 'md', 'docx'], true)) {
            return response()->json(['message' => 'Unsupported format.'], 422);
        }

        $file = $technicalAuditJob->files()->where('format', $format)->first();
        if (! $file) {
            return response()->json(['message' => 'Файл ещё не готов.'], 404);
        }

        $disk = (string) config('technical_audit.storage_disk', 'local');
        $mime = match ($format) {
            'json' => 'application/json',
            'md' => 'text/markdown; charset=UTF-8',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        };

        $domain = parse_url($technicalAuditJob->site_url, PHP_URL_HOST) ?: 'site';
        $filename = sprintf('%s_technical_audit.%s', str_replace('.', '-', $domain), $format);

        return Storage::disk($disk)->download($file->path, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    private function serialize(TechnicalAuditJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'site_url' => $job->site_url,
            'site_name' => $job->site_name,
            'sample_urls' => $job->sample_urls ?? [],
            'crawl_depth' => $job->crawl_depth,
            'lang' => $job->lang,
            'cursor_agent_id' => $job->cursor_agent_id,
            'cursor_agent_url' => $job->cursorAgentUrl(),
            'webhook_reachable' => $job->webhookReachable(),
            'result_summary' => $job->result_summary,
            'activity_log' => $job->activity_log ?? [],
            'error_message' => $job->error_message,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'created_at' => $job->created_at,
            'project' => $job->relationLoaded('project') ? [
                'id' => $job->project->id,
                'name' => $job->project->name,
                'domain' => $job->project->domain,
            ] : null,
            'files' => $job->relationLoaded('files')
                ? $job->files->map(fn ($f) => ['format' => $f->format, 'size' => $f->size])->values()
                : [],
        ];
    }
}
