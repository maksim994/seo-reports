<?php

namespace App\Models;

use App\Enums\TechnicalAuditJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TechnicalAuditJob extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'status',
        'site_url',
        'site_name',
        'sample_urls',
        'crawl_depth',
        'lang',
        'cursor_agent_id',
        'cursor_run_id',
        'webhook_token',
        'result_summary',
        'activity_log',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TechnicalAuditJobStatus::class,
            'sample_urls' => 'array',
            'result_summary' => 'array',
            'activity_log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TechnicalAuditJob $job) {
            if (empty($job->webhook_token)) {
                $job->webhook_token = Str::random(64);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(TechnicalAuditFile::class);
    }

    public function webhookUrl(): ?string
    {
        $baseUrl = rtrim((string) config('technical_audit.webhook_base_url', ''), '/');
        if ($baseUrl === '' || $this->isLocalhostUrl($baseUrl)) {
            return null;
        }

        return $baseUrl.'/api/webhooks/technical-audits/'.$this->webhook_token;
    }

    public function webhookReachable(): bool
    {
        return $this->webhookUrl() !== null;
    }

    private function isLocalhostUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', 'app', 'nginx'], true);
    }

    public function cursorAgentUrl(): ?string
    {
        if ($this->cursor_agent_id === null) {
            return null;
        }

        return 'https://cursor.com/agents/'.$this->cursor_agent_id;
    }
}
