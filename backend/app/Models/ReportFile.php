<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportFile extends Model
{
    protected $fillable = [
        'report_job_id',
        'format',
        'path',
        'size',
    ];

    public function reportJob(): BelongsTo
    {
        return $this->belongsTo(ReportJob::class);
    }
}
