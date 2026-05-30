<?php

namespace App\Jobs;

use App\Enums\TechnicalAuditJobStatus;
use App\Models\TechnicalAuditJob;
use App\Services\TechnicalAuditRecoveryService;
use App\Services\CursorAgentClient;
use App\Services\TechnicalAuditActivityLogger;
use App\Services\TechnicalAuditDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PollTechnicalAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public TechnicalAuditJob $technicalAuditJob,
        public int $attempt = 1,
    ) {}

    public function handle(
        CursorAgentClient $cursor,
        TechnicalAuditDeliveryService $delivery,
        TechnicalAuditActivityLogger $logger,
    ): void {
        $job = $this->technicalAuditJob->fresh(['files']);
        if (! $job || in_array($job->status, [TechnicalAuditJobStatus::Done, TechnicalAuditJobStatus::Failed, TechnicalAuditJobStatus::Processing], true)) {
            return;
        }

        if ($job->files()->exists()) {
            $job->update([
                'status' => TechnicalAuditJobStatus::Done,
                'finished_at' => $job->finished_at ?? now(),
            ]);

            return;
        }

        if ($job->cursor_agent_id === null) {
            $this->failJob($job, $logger, 'Cursor agent id is missing.');

            return;
        }

        try {
            if ($this->attempt === 1) {
                $logger->info($job, 'Начато отслеживание прогресса Cloud Agent.');
            } elseif ($this->attempt % 5 === 0) {
                $elapsed = $job->started_at?->diffInMinutes(now()) ?? 0;
                $logger->info($job, 'Агент всё ещё выполняет аудит.', [
                    'poll_attempt' => $this->attempt,
                    'elapsed_minutes' => $elapsed,
                    'cursor_run_status' => 'RUNNING',
                ]);
            }

            $agent = $cursor->getAgent($job->cursor_agent_id);
            $runId = (string) ($agent['latestRunId'] ?? $job->cursor_run_id ?? '');
            if ($runId === '') {
                $logger->warning($job, 'Ожидание старта run у Cloud Agent.', [
                    'poll_attempt' => $this->attempt,
                ]);
                $this->scheduleNext($job);

                return;
            }

            if ($job->cursor_run_id !== $runId) {
                $job->update(['cursor_run_id' => $runId]);
            }

            $run = $cursor->getRun($job->cursor_agent_id, $runId);
            $status = (string) ($run['status'] ?? '');

            if (in_array($status, ['CREATING', 'RUNNING'], true)) {
                if ($this->attempt === 1 || $this->attempt % 5 === 0) {
                    $logger->info($job, 'Cursor Agent выполняет проверки сайта.', [
                        'cursor_run_status' => $status,
                        'poll_attempt' => $this->attempt,
                        'cursor_agent_url' => $job->cursorAgentUrl(),
                    ]);
                }
                $this->scheduleNext($job);

                return;
            }

            if ($status === 'FINISHED') {
                $logger->info($job, 'Cloud Agent завершил run. Ожидаем webhook с JSON-отчётом.', [
                    'cursor_run_status' => $status,
                ]);

                $payload = $delivery->extractJsonFromText((string) ($run['result'] ?? ''));
                if ($payload !== null) {
                    $logger->info($job, 'JSON найден в ответе агента. Сохраняем файлы...');
                    $delivery->deliver($job, $payload);

                    return;
                }

                if (! $job->webhookReachable() && $this->attempt >= 3) {
                    $logger->warning($job, 'Webhook недоступен (localhost). Запускаем recovery через Cursor API.');
                    app(TechnicalAuditRecoveryService::class)->startJsonRecovery($job);

                    return;
                }

                if ($this->attempt >= (int) config('technical_audit.poll_max_attempts', 60)) {
                    if (! $job->webhookReachable()) {
                        app(TechnicalAuditRecoveryService::class)->startJsonRecovery($job);

                        return;
                    }

                    $this->failJob($job, $logger, 'Аудит завершился, но webhook и JSON в ответе агента не получены.');
                } else {
                    $this->scheduleNext($job);
                }

                return;
            }

            $this->failJob($job, $logger, 'Cursor agent run failed with status: '.$status);
        } catch (Throwable $e) {
            if ($this->attempt >= (int) config('technical_audit.poll_max_attempts', 60)) {
                $this->failJob($job, $logger, $e->getMessage());
            } else {
                if ($this->attempt === 1 || $this->attempt % 5 === 0) {
                    $logger->warning($job, 'Временная ошибка при опросе статуса. Повторим.', [
                        'poll_attempt' => $this->attempt,
                        'error' => $e->getMessage(),
                    ]);
                }
                $this->scheduleNext($job);
            }
        }
    }

    private function scheduleNext(TechnicalAuditJob $job): void
    {
        if ($this->attempt >= (int) config('technical_audit.poll_max_attempts', 60)) {
            $this->failJob($job, app(TechnicalAuditActivityLogger::class), 'Превышено время ожидания технического аудита.');

            return;
        }

        self::dispatch($job, $this->attempt + 1)
            ->delay(now()->addSeconds((int) config('technical_audit.poll_interval_seconds', 30)));
    }

    private function failJob(TechnicalAuditJob $job, TechnicalAuditActivityLogger $logger, string $message): void
    {
        $logger->error($job, $message);
        $job->update([
            'status' => TechnicalAuditJobStatus::Failed,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
