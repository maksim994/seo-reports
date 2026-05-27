<?php

namespace App\Models;

use App\Enums\WorkItemCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkItem extends Model
{
    protected $fillable = [
        'project_id',
        'work_date',
        'category',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'category' => WorkItemCategory::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
