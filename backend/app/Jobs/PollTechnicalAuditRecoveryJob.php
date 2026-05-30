<?php

namespace App\Jobs;

use App\Enums\TechnicalAuditJobStatus;
use App\Models\TechnicalAuditJob;
use App\Services\CursorAgentClient;
use App\Services\TechnicalAuditActivityLogger;
use App\Services\TechnicalAuditDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PollTechnicalAuditRecoveryJob implements ShouldQueue
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
        if (! $job || $job->files()->exists()) {
            return;
        }

        if ($job->cursor_agent_id === null || $job->cursor_run_id === null) {
            return;
        }

        try {
            $run = $cursor->getRun($job->cursor_agent_id, $job->cursor_run_id);
            $status = (string) ($run['status'] ?? '');

            if (in_array($status, ['CREATING', 'RUNNING'], true)) {
                if ($this->attempt >= 40) {
                    $logger->error($job, 'Не удалось получить JSON от Cloud Agent.');
                    $job->update([
                        'status' => TechnicalAuditJobStatus::Failed,
                        'error_message' => 'Не удалось получить JSON от Cloud Agent. Импортируйте JSON вручную.',
                        'finished_at' => now(),
                    ]);

                    return;
                }

                self::dispatch($job, $this->attempt + 1)
                    ->delay(now()->addSeconds(15));

                return;
            }

            if ($status !== 'FINISHED') {
                $logger->error($job, 'Recovery run завершился со статусом: '.$status);
                $job->update([
                    'status' => TechnicalAuditJobStatus::Failed,
                    'error_message' => 'Recovery run завершился со статусом: '.$status,
                    'finished_at' => now(),
                ]);

                return;
            }

            $payload = $delivery->extractJsonFromText((string) ($run['result'] ?? ''));
            if ($payload === null) {
                $logger->warning($job, 'JSON не найден в ответе recovery run. Попробуйте импорт вручную.');
                $job->update([
            'status' => TechnicalAuditJobStatus::Failed,
            'error_message' => 'JSON не найден в ответе агента. Загрузите JSON-файл вручную.',
            'finished_at' => now(),
        ]);

                return;
            }

            $logger->success($job, 'JSON получен от Cloud Agent через recovery run.');
            $delivery->deliver($job, $payload);
        } catch (Throwable $e) {
            if ($this->attempt >= 40) {
                $logger->error($job, $e->getMessage());
                $job->update([
                    'status' => TechnicalAuditJobStatus::Failed,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            } else {
                self::dispatch($job, $this->attempt + 1)
                    ->delay(now()->addSeconds(15));
            }
        }
    }
}
