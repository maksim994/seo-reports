<?php

namespace App\Jobs;

use App\Enums\TechnicalAuditJobStatus;
use App\Models\TechnicalAuditJob;
use App\Services\CursorAgentClient;
use App\Services\TechnicalAuditActivityLogger;
use App\Services\TechnicalAuditPromptBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunTechnicalAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public TechnicalAuditJob $technicalAuditJob) {}

    public function handle(
        CursorAgentClient $cursor,
        TechnicalAuditPromptBuilder $promptBuilder,
        TechnicalAuditActivityLogger $logger,
    ): void {
        $job = $this->technicalAuditJob->fresh();
        if (! $job || $job->status !== TechnicalAuditJobStatus::Queued) {
            return;
        }

        $logger->info($job, 'Аудит поставлен в очередь на запуск.');

        if (! $cursor->isConfigured()) {
            $logger->error($job, 'CURSOR_API_KEY не настроен.');
            $job->update([
                'status' => TechnicalAuditJobStatus::Failed,
                'error_message' => 'CURSOR_API_KEY не настроен. Добавьте ключ в .env.',
                'finished_at' => now(),
            ]);

            return;
        }

        $job->update([
            'status' => TechnicalAuditJobStatus::Launching,
            'started_at' => now(),
            'error_message' => null,
        ]);
        $logger->info($job, 'Запуск Cursor Cloud Agent...', [
            'repo' => config('technical_audit.cursor_repo_url'),
        ]);

        try {
            $created = $cursor->createAgent($promptBuilder->build($job));

            $job->update([
                'status' => TechnicalAuditJobStatus::Running,
                'cursor_agent_id' => $created['agent']['id'],
                'cursor_run_id' => $created['run']['id'],
            ]);

            $logger->success($job, 'Cloud Agent запущен. Выполняется pipeline skills-seo-audit.', [
                'cursor_agent_id' => $created['agent']['id'],
                'cursor_run_id' => $created['run']['id'],
                'cursor_agent_url' => $job->cursorAgentUrl(),
            ]);

            PollTechnicalAuditJob::dispatch($job, 1)
                ->delay(now()->addSeconds((int) config('technical_audit.poll_interval_seconds', 30)));
        } catch (Throwable $e) {
            $logger->error($job, 'Не удалось запустить Cloud Agent.', [
                'error' => $e->getMessage(),
            ]);
            $job->update([
                'status' => TechnicalAuditJobStatus::Failed,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
