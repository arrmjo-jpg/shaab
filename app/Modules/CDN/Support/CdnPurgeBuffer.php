<?php

declare(strict_types=1);

namespace App\Modules\CDN\Support;

use Illuminate\Support\Facades\Cache;

/**
 * مخزّن مؤقت لدفعات الـ purge — يُجمَّع ثم يُفرَّغ على شكل دفعات
 * بحجم قيد Cloudflare (purge_chunk).
 */
final class CdnPurgeBuffer
{
    private const KEY = 'cdn:purge:buffer';

    private const TTL = 3600;

    public function add(array $urls): void
    {
        $existing = Cache::get(self::KEY, []);
        $merged = array_values(array_unique(array_merge($existing, array_filter($urls))));
        Cache::put(self::KEY, $merged, self::TTL);
    }

    public function size(): int
    {
        return count(Cache::get(self::KEY, []));
    }

    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    /**
     * يُرجع الدفعات مقسّمة ويُفرّغ المخزّن.
     *
     * @return array<int, array<int, string>>
     */
    public function flushChunks(): array
    {
        $urls = Cache::get(self::KEY, []);
        Cache::forget(self::KEY);

        if ($urls === []) {
            return [];
        }

        $chunk = max(1, (int) config('cdn.api.purge_chunk', 30));

        return array_chunk($urls, $chunk);
    }
}
