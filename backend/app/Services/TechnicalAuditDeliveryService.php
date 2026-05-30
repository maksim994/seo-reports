<?php

namespace App\Services;

use App\Enums\TechnicalAuditJobStatus;
use App\Models\TechnicalAuditFile;
use App\Models\TechnicalAuditJob;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TechnicalAuditDeliveryService
{
    public function __construct(
        private TechnicalAuditActivityLogger $logger,
    ) {}
    /**
     * @param  array<string, mixed>  $payload
     */
    public function deliver(TechnicalAuditJob $job, array $payload): void
    {
        if (! isset($payload['checks']) && ! isset($payload['totals'])) {
            throw new RuntimeException('Audit payload must include checks or totals.');
        }

        $job->update(['status' => TechnicalAuditJobStatus::Processing]);
        $this->logger->info($job, 'Формируем файлы отчёта (JSON, Markdown, DOCX)...');

        $disk = (string) config('technical_audit.storage_disk', 'local');
        $basePath = config('technical_audit.storage_path_prefix', 'technical-audits').'/'.$job->id;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Failed to encode audit JSON.');
        }

        $this->storeFile($job, $disk, $basePath.'/audit.json', 'json', $json);

        $markdown = is_string($payload['markdown'] ?? null)
            ? $payload['markdown']
            : $this->buildMarkdown($payload);

        $this->storeFile($job, $disk, $basePath.'/audit.md', 'md', $markdown);

        $this->generateDocx($job, $disk, $basePath, $payload);

        $formats = $job->fresh('files')->files->pluck('format')->all();
        $this->logger->success($job, 'Технический аудит готов.', [
            'files' => $formats,
            'totals' => $payload['totals'] ?? null,
        ]);

        $job->update([
            'status' => TechnicalAuditJobStatus::Done,
            'result_summary' => [
                'totals' => $payload['totals'] ?? null,
                'top_priorities' => $payload['top_priorities'] ?? [],
            ],
            'finished_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function buildMarkdown(array $payload): string
    {
        $siteUrl = (string) ($payload['site_url'] ?? '');
        $siteName = (string) ($payload['site_name'] ?? $siteUrl);
        $auditDate = (string) ($payload['audit_date'] ?? now()->format('Y-m-d'));
        $totals = $payload['totals'] ?? [];
        $checks = $payload['checks'] ?? [];

        $lines = [
            '# Технический аудит сайта',
            '',
            "- Сайт: {$siteUrl}",
            "- Компания: {$siteName}",
            "- Дата аудита: {$auditDate}",
            '',
            '> Данный отчёт содержит перечень обнаруженных ошибок и не является готовым техническим заданием для конечных исполнителей.',
            '',
            '## Сводка',
            '',
            sprintf(
                '- Критичных: %s',
                (string) ($totals['critical'] ?? 0),
            ),
            sprintf(
                '- Замечаний: %s',
                (string) ($totals['warning'] ?? 0),
            ),
            sprintf(
                '- Без проблем: %s',
                (string) ($totals['ok'] ?? 0),
            ),
            '',
        ];

        $priorities = $payload['top_priorities'] ?? [];
        if (is_array($priorities) && $priorities !== []) {
            $lines[] = '## Приоритет исправлений';
            $lines[] = '';
            foreach ($priorities as $priority) {
                $lines[] = '- '.$priority;
            }
            $lines[] = '';
        }

        $lines[] = '## Проверки';
        $lines[] = '';

        foreach ($checks as $check) {
            if (! is_array($check)) {
                continue;
            }

            $title = (string) ($check['title'] ?? $check['id'] ?? 'Проверка');
            $status = (string) ($check['status'] ?? 'warning');
            $finding = (string) ($check['finding'] ?? '');

            $lines[] = "### {$title}";
            $lines[] = '';
            $lines[] = "**Статус:** {$status}";
            $lines[] = '';
            $lines[] = $finding;
            $lines[] = '';

            $evidence = $check['evidence'] ?? [];
            if (is_array($evidence) && $evidence !== []) {
                $lines[] = '**Доказательства:**';
                foreach (array_slice($evidence, 0, 20) as $item) {
                    $lines[] = '- '.(string) $item;
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function extractJsonFromText(string $text): ?array
    {
        if ($text === '') {
            return null;
        }

        if (preg_match('/```json\s*([\s\S]*?)```/u', $text, $matches) === 1) {
            $decoded = json_decode(trim($matches[1]), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function storeFile(
        TechnicalAuditJob $job,
        string $disk,
        string $path,
        string $format,
        string $contents,
    ): void {
        Storage::disk($disk)->put($path, $contents);

        TechnicalAuditFile::updateOrCreate(
            [
                'technical_audit_job_id' => $job->id,
                'format' => $format,
            ],
            [
                'path' => $path,
                'size' => strlen($contents),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function generateDocx(
        TechnicalAuditJob $job,
        string $disk,
        string $basePath,
        array $payload,
    ): void {
        $script = (string) config('technical_audit.docx_script');
        if (! is_file($script)) {
            return;
        }

        $tempDir = storage_path('app/temp/technical-audits/'.$job->id);
        if (! is_dir($tempDir) && ! mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return;
        }

        $inputPath = $tempDir.'/audit.json';
        $outputPath = $tempDir.'/audit.docx';

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return;
        }

        file_put_contents($inputPath, $json);

        try {
            $result = Process::timeout(120)->run([
                'python3',
                $script,
                '--input',
                $inputPath,
                '--output',
                $outputPath,
            ]);

            if (! $result->successful() || ! is_file($outputPath)) {
                $this->logger->warning($job, 'DOCX не сгенерирован: ошибка Python-скрипта.', [
                    'stderr' => trim($result->errorOutput()),
                ]);

                return;
            }

            $docxContents = file_get_contents($outputPath);
            if ($docxContents === false) {
                return;
            }

            $storedPath = $basePath.'/audit.docx';
            Storage::disk($disk)->put($storedPath, $docxContents);

            TechnicalAuditFile::updateOrCreate(
                [
                    'technical_audit_job_id' => $job->id,
                    'format' => 'docx',
                ],
                [
                    'path' => $storedPath,
                    'size' => strlen($docxContents),
                ],
            );
            $this->logger->info($job, 'DOCX успешно сгенерирован.');
        } catch (Throwable) {
            $this->logger->warning($job, 'DOCX не сгенерирован: Python или python-docx недоступны.');
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }
}
