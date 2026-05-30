<?php

namespace App\Services;

use App\Models\TechnicalAuditJob;

class TechnicalAuditActivityLogger
{
    private const MAX_ENTRIES = 100;

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        TechnicalAuditJob $job,
        string $level,
        string $message,
        array $context = [],
    ): void {
        $job->refresh();
        $entries = $job->activity_log ?? [];

        $entries[] = [
            'at' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
            'context' => $context !== [] ? $context : null,
        ];

        if (count($entries) > self::MAX_ENTRIES) {
            $entries = array_slice($entries, -self::MAX_ENTRIES);
        }

        $job->update(['activity_log' => $entries]);
    }

    public function info(TechnicalAuditJob $job, string $message, array $context = []): void
    {
        $this->log($job, 'info', $message, $context);
    }

    public function success(TechnicalAuditJob $job, string $message, array $context = []): void
    {
        $this->log($job, 'success', $message, $context);
    }

    public function warning(TechnicalAuditJob $job, string $message, array $context = []): void
    {
        $this->log($job, 'warning', $message, $context);
    }

    public function error(TechnicalAuditJob $job, string $message, array $context = []): void
    {
        $this->log($job, 'error', $message, $context);
    }
}
