<?php

namespace App\Services;

use App\Enums\TechnicalAuditJobStatus;
use App\Jobs\PollTechnicalAuditRecoveryJob;
use App\Models\TechnicalAuditJob;

class TechnicalAuditRecoveryService
{
    public function __construct(
        private CursorAgentClient $cursor,
        private TechnicalAuditPromptBuilder $promptBuilder,
        private TechnicalAuditActivityLogger $logger,
    ) {}

    public function startJsonRecovery(TechnicalAuditJob $job): void
    {
        if ($job->cursor_agent_id === null) {
            throw new \RuntimeException('Cloud Agent не был запущен для этой задачи.');
        }

        if ($job->files()->exists()) {
            return;
        }

        $this->logger->info($job, 'Запрашиваем JSON-отчёт у Cloud Agent (webhook недоступен).');

        $followUp = $this->cursor->createFollowUpRun(
            $job->cursor_agent_id,
            $this->promptBuilder->buildJsonRecoveryPrompt($job),
        );

        $job->update([
            'status' => TechnicalAuditJobStatus::Running,
            'cursor_run_id' => $followUp['run']['id'],
        ]);

        PollTechnicalAuditRecoveryJob::dispatch($job, 1)
            ->delay(now()->addSeconds(15));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function importPayload(TechnicalAuditJob $job, array $payload, TechnicalAuditDeliveryService $delivery): void
    {
        if ($job->files()->exists()) {
            throw new \RuntimeException('Отчёт уже сохранён.');
        }

        $this->logger->info($job, 'JSON импортирован вручную.');
        $delivery->deliver($job, $payload);
    }
}
