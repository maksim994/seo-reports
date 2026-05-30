<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'promotion_start_date',
        'has_analytics',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'promotion_start_date' => 'date',
            'has_analytics' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projectIntegrations(): HasMany
    {
        return $this->hasMany(ProjectIntegration::class);
    }

    public function reportJobs(): HasMany
    {
        return $this->hasMany(ReportJob::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function technicalAuditJobs(): HasMany
    {
        return $this->hasMany(TechnicalAuditJob::class);
    }
}
