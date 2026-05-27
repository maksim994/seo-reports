<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class KeysSoRateLimiter
{
    private const CACHE_KEY = 'keysso:request_timestamps';

    public function waitForSlot(): void
    {
        $max = max(1, (int) config('keysso.rate_limit_max', 10));
        $window = max(1, (int) config('keysso.rate_limit_window', 10));

        while (true) {
            $now = microtime(true);
            $timestamps = Cache::get(self::CACHE_KEY, []);
            if (! is_array($timestamps)) {
                $timestamps = [];
            }

            $timestamps = array_values(array_filter(
                $timestamps,
                static fn ($timestamp) => is_numeric($timestamp) && ($now - (float) $timestamp) < $window,
            ));

            if (count($timestamps) < $max) {
                $timestamps[] = $now;
                Cache::put(self::CACHE_KEY, $timestamps, $window + 1);

                return;
            }

            usleep(200_000);
        }
    }
}
