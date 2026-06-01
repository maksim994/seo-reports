<?php

namespace App\Support;

class MetrikaBlockSettings
{
    /** @var array<string, string> */
    public const TRAFFIC_SOURCE_OPTIONS = [
        '' => 'Все каналы',
        'organic' => 'Поисковые системы',
        'direct' => 'Прямые заходы',
        'ad' => 'Рекламный трафик',
        'referral' => 'Переходы по ссылкам',
        'social' => 'Социальные сети',
        'email' => 'Email',
        'messenger' => 'Мессенджеры',
        'recommendation' => 'Рекомендательные системы',
        'internal' => 'Внутренние переходы',
    ];

    /** @return list<int>|null */
    public static function goalIds(?array $settings): ?array
    {
        if ($settings === null) {
            return null;
        }

        $raw = $settings['goal_ids'] ?? null;
        if ($raw === null || $raw === '' || $raw === []) {
            return null;
        }

        if (is_string($raw)) {
            $parts = preg_split('/\s*,\s*/', $raw) ?: [];

            return self::normalizeGoalIds($parts);
        }

        if (is_array($raw)) {
            return self::normalizeGoalIds($raw);
        }

        return null;
    }

    public static function trafficSource(?array $settings): ?string
    {
        if ($settings === null) {
            return null;
        }

        $value = $settings['traffic_source'] ?? null;
        if (! is_string($value) || $value === '' || $value === 'all') {
            return null;
        }

        return $value;
    }

    public static function trafficSourceFilter(?array $settings): ?string
    {
        $source = self::trafficSource($settings);

        return $source !== null ? "ym:s:lastTrafficSource=='{$source}'" : null;
    }

    /**
     * Настройки блока шаблона перекрывают значения из config привязки проекта.
     *
     * @return array{goal_ids: list<int>|null, traffic_source: string|null}
     */
    public static function resolve(?array $blockSettings, ?array $bindingConfig): array
    {
        $defaults = is_array($bindingConfig['metrika'] ?? null) ? $bindingConfig['metrika'] : [];

        return [
            'goal_ids' => self::goalIds($blockSettings) ?? self::goalIds($defaults),
            'traffic_source' => self::trafficSource($blockSettings) ?? self::trafficSource($defaults),
        ];
    }

    /** @param  list<mixed>  $parts */
    private static function normalizeGoalIds(array $parts): ?array
    {
        $ids = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids === [] ? null : array_values(array_unique($ids));
    }
}
