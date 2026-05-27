<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'app_settings';

    public const DEFAULTS = [
        'app_name' => 'SEO Reports',
        'support_email' => 'support@seo-reports.local',
        'registration_enabled' => true,
        'email_verification_required' => false,
        'report_retention_months' => 12,
        'maintenance_mode' => false,
        'maintenance_message' => '',
    ];

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $stored = Setting::query()->pluck('value', 'key')->toArray();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public function setMany(array $settings): array
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    public function publicSettings(): array
    {
        return [
            'app_name' => $this->get('app_name'),
            'registration_enabled' => (bool) $this->get('registration_enabled'),
            'maintenance_mode' => (bool) $this->get('maintenance_mode'),
            'maintenance_message' => (string) $this->get('maintenance_message'),
        ];
    }
}
