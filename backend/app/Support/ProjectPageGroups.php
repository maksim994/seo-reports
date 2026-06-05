<?php

namespace App\Support;

class ProjectPageGroups
{
    public const SETTINGS_KEY = 'page_groups';

    private const MAX_GROUPS = 30;

    /**
     * @param  mixed  $groups
     * @return list<array{id: string, label: string, pattern: string, enabled: bool}>
     */
    public static function normalize(mixed $groups): array
    {
        if (! is_array($groups)) {
            return [];
        }

        $normalized = [];
        foreach ($groups as $index => $group) {
            if (! is_array($group)) {
                continue;
            }

            $label = trim((string) ($group['label'] ?? ''));
            $pattern = trim((string) ($group['pattern'] ?? ''));

            if ($label === '' || $pattern === '' || ! self::isValidRegex($pattern)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($group['id'] ?? 'group-'.$index),
                'label' => mb_substr($label, 0, 80),
                'pattern' => $pattern,
                'enabled' => filter_var($group['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];

            if (count($normalized) >= self::MAX_GROUPS) {
                break;
            }
        }

        return $normalized;
    }

    public static function isValidRegex(string $pattern): bool
    {
        set_error_handler(static fn () => true);
        $result = preg_match(self::compile($pattern), '/test/path/');
        restore_error_handler();

        return $result !== false;
    }

    /**
     * @param  list<array{id: string, label: string, pattern: string, enabled: bool}>  $groups
     */
    public static function matchLabel(string $url, array $groups, bool $includeOther = true): ?string
    {
        $path = self::pathFromUrl($url);

        foreach ($groups as $group) {
            if (! ($group['enabled'] ?? true)) {
                continue;
            }

            if (preg_match(self::compile($group['pattern']), $path) === 1) {
                return $group['label'];
            }
        }

        return $includeOther ? 'Прочее' : null;
    }

    public static function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return $path;
        }

        if (str_starts_with($url, '/')) {
            return strtok($url, '?') ?: '/';
        }

        return '/';
    }

    private static function compile(string $pattern): string
    {
        return '~'.str_replace('~', '\\~', $pattern).'~u';
    }
}
