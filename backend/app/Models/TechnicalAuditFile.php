<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalAuditFile extends Model
{
    protected $fillable = [
        'technical_audit_job_id',
        'format',
        'path',
        'size',
    ];

    public function technicalAuditJob(): BelongsTo
    {
        return $this->belongsTo(TechnicalAuditJob::class);
    }
}
