<?php

namespace App\Models;

use App\Enums\ReportJobStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportJob extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'report_template_id',
        'status',
        'period_start',
        'period_end',
        'compare_period_start',
        'compare_period_end',
        'options',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReportJobStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'compare_period_start' => 'date',
            'compare_period_end' => 'date',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReportFile::class);
    }
}
