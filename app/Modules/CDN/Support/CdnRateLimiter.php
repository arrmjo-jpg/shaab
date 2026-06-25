<?php

declare(strict_types=1);

namespace App\Modules\CDN\Support;

use Illuminate\Support\Facades\Cache;

/**
 * تحديد معدّل نداء Cloudflare API (نافذة ثابتة عبر Cache).
 * حماية آمنة من تجاوز حدود الـ API.
 */
final class CdnRateLimiter
{
    private const KEY = 'cdn:ratelimit:cloudflare';

    public function allow(): bool
    {
        $max = (int) config('cdn.rate_limit.max', 1000);
        $window = (int) config('cdn.rate_limit.window', 300);

        $current = (int) Cache::get(self::KEY, 0);

        if ($current >= $max) {
            return false;
        }

        if ($current === 0) {
            Cache::put(self::KEY, 1, $window);
        } else {
            Cache::increment(self::KEY);
        }

        return true;
    }
}
