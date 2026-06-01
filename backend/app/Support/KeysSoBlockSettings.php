<?php

namespace App\Support;

class KeysSoBlockSettings
{
    public const BASE_OPTIONS = [
        'msk' => 'Яндекс: Москва',
        'spb' => 'Яндекс: Санкт-Петербург',
        'gru' => 'Google: Москва',
        'gny' => 'Google: New York',
    ];

    public static function base(?array $settings, string $default = 'msk'): string
    {
        $base = (string) ($settings['base'] ?? $default);

        return array_key_exists($base, self::BASE_OPTIONS) ? $base : $default;
    }

    public static function limit(?array $settings, int $default = 25, int $max = 100): int
    {
        $limit = (int) ($settings['limit'] ?? $default);

        return max(1, min($max, $limit > 0 ? $limit : $default));
    }
}
