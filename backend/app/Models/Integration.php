<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'credentials',
        'status',
        'account_label',
        'expires_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'status' => IntegrationStatus::class,
            'credentials' => 'encrypted:array',
            'expires_at' => 'datetime',
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
}
