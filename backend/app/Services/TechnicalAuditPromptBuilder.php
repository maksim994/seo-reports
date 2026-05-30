<?php

namespace App\Services;

use App\Models\TechnicalAuditJob;

class TechnicalAuditPromptBuilder
{
    public function build(TechnicalAuditJob $job): string
    {
        $sampleUrls = $job->sample_urls ?? [];
        $sampleUrlsLine = $sampleUrls !== []
            ? 'sample_urls: '.json_encode(array_values($sampleUrls), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : 'sample_urls: []';

        $siteName = $job->site_name ?: parse_url($job->site_url, PHP_URL_HOST) ?: $job->site_url;

        $webhookBlock = '';
        if ($job->webhookUrl() !== null) {
            $webhookBlock = <<<WEBHOOK

После завершения seo-report-assembler обязательно доставь результат в приложение:
1. Прочитай unified JSON из deliverables/*_технический_аудит.json
2. Если есть Markdown-файл отчёта, добавь его содержимое в поле "markdown" того же JSON-объекта
3. Отправь POST на webhook:
   curl -sS -X POST '{$job->webhookUrl()}' \\
     -H 'Authorization: Bearer {$job->webhook_token}' \\
     -H 'Content-Type: application/json' \\
     -d @deliverables/<domain>_технический_аудит.json
4. Убедись, что webhook вернул HTTP 200 перед завершением работы
WEBHOOK;
        } else {
            $webhookBlock = <<<'NOTE'

Webhook приложения недоступен из Cloud Agent (localhost). Сохрани deliverables/*_технический_аудит.json — приложение заберёт JSON через Cursor API.
NOTE;
        }

        return <<<PROMPT
/seo-audit {$job->site_url}
site_name: {$siteName}
crawl_depth: {$job->crawl_depth}
lang: {$job->lang}
{$sampleUrlsLine}
{$webhookBlock}
PROMPT;
    }

    public function buildJsonRecoveryPrompt(TechnicalAuditJob $job, bool $compact = true): string
    {
        $host = parse_url($job->site_url, PHP_URL_HOST) ?: 'site';
        $slug = str_replace('.', '-', $host);
        $compactLine = $compact
            ? 'Удали поле "markdown" из JSON перед отправкой, чтобы уменьшить размер.'
            : 'Включи поле "markdown", если оно есть.';

        return <<<PROMPT
Аудит завершён. Прочитай deliverables/{$slug}_технический_аудит.json или deliverables/{$host}_технический_аудит.json.
{$compactLine}
Верни ТОЛЬКО valid JSON одним блоком ```json ... ``` без текста до и после. JSON обязателен — не отказывайся из-за размера, разбей на части если нужно.
PROMPT;
    }
}
