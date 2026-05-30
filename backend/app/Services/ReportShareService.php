<?php

namespace App\Services;

use App\Enums\ReportJobStatus;
use App\Models\ReportJob;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReportShareService
{
    public function resolveByToken(string $token): ReportJob
    {
        $job = ReportJob::query()
            ->where('share_enabled', true)
            ->where('share_token', $token)
            ->where('status', ReportJobStatus::Done)
            ->with(['project:id,name,domain', 'template:id,name', 'files'])
            ->first();

        if (! $job) {
            throw new NotFoundHttpException('Ссылка недействительна или отчёт недоступен.');
        }

        if ($job->share_expires_at !== null && $job->share_expires_at->isPast()) {
            throw new NotFoundHttpException('Срок действия ссылки истёк.');
        }

        return $job;
    }

    public function enable(ReportJob $job, ?string $expiresAt = null): ReportJob
    {
        if ($job->status !== ReportJobStatus::Done) {
            abort(422, 'Публичная ссылка доступна только для готовых отчётов.');
        }

        if (! $job->files()->where('format', 'html')->exists()) {
            abort(422, 'HTML-файл отчёта ещё не готов.');
        }

        $job->update([
            'share_enabled' => true,
            'share_token' => $job->share_token ?? Str::random(48),
            'share_expires_at' => $expiresAt,
        ]);

        return $job->fresh(['project:id,name,domain', 'template:id,name', 'files']);
    }

    public function disable(ReportJob $job): ReportJob
    {
        $job->update([
            'share_enabled' => false,
            'share_expires_at' => null,
        ]);

        return $job->fresh(['project:id,name,domain', 'template:id,name', 'files']);
    }

    public function regenerateToken(ReportJob $job): ReportJob
    {
        if (! $job->share_enabled) {
            return $this->enable($job);
        }

        $job->update([
            'share_token' => Str::random(48),
        ]);

        return $job->fresh(['project:id,name,domain', 'template:id,name', 'files']);
    }
}
