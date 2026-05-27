<?php

namespace App\Support;

use App\Services\ReportFetchCache;
use Illuminate\Support\Facades\Cache;

class ReportFetch
{
    public static function remember(string $key, callable $callback): mixed
    {
        if (app()->bound(ReportFetchCache::class)) {
            $memory = app(ReportFetchCache::class);
            if ($memory->has($key)) {
                return $memory->get($key);
            }
        }

        $redisKey = 'report_fetch:'.sha1($key);
        if (self::redisCacheEnabled()) {
            $cached = Cache::get($redisKey);
            if ($cached !== null) {
                self::rememberInMemory($key, $cached);

                return $cached;
            }
        }

        $result = $callback();

        self::rememberInMemory($key, $result);

        if (self::redisCacheEnabled()) {
            Cache::put($redisKey, $result, (int) config('reports.api_cache_ttl', 3600));
        }

        return $result;
    }

    private static function redisCacheEnabled(): bool
    {
        if (! config('reports.api_cache_enabled', true)) {
            return false;
        }

        return config('cache.default') !== 'array';
    }

    private static function rememberInMemory(string $key, mixed $value): void
    {
        if (! app()->bound(ReportFetchCache::class)) {
            return;
        }

        app(ReportFetchCache::class)->remember($key, fn () => $value);
    }
}
