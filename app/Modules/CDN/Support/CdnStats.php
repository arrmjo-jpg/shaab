<?php

declare(strict_types=1);

namespace App\Modules\CDN\Support;

use Illuminate\Support\Facades\Cache;

/**
 * إحصاءات CDN الدنيا لخدمة /cdn/status فقط (لا Prometheus).
 */
final class CdnStats
{
    private const PREFIX = 'cdn:stats:';

    private const TTL = 604800; // أسبوع

    public function recordPurge(int $count, bool $success): void
    {
        $this->bump($success ? 'purge_success' : 'purge_failed', 1);
        $this->bump('purged_urls', $count);
        Cache::put(self::PREFIX.'last_purge_at', now()->toISOString(), self::TTL);
    }

    public function recordTest(bool $success): void
    {
        Cache::put(self::PREFIX.'last_test_ok', $success, self::TTL);
        Cache::put(self::PREFIX.'last_test_at', now()->toISOString(), self::TTL);
    }

    public function summary(): array
    {
        return [
            'purge_success' => (int) Cache::get(self::PREFIX.'purge_success', 0),
            'purge_failed' => (int) Cache::get(self::PREFIX.'purge_failed', 0),
            'purged_urls' => (int) Cache::get(self::PREFIX.'purged_urls', 0),
            'last_purge_at' => Cache::get(self::PREFIX.'last_purge_at'),
            'last_test_ok' => Cache::get(self::PREFIX.'last_test_ok'),
            'last_test_at' => Cache::get(self::PREFIX.'last_test_at'),
        ];
    }

    // زيادة ذرّية: add يثبّت TTL إن غاب المفتاح، increment ذرّي
    private function bump(string $key, int $by): void
    {
        if ($by <= 0) {
            return;
        }

        $full = self::PREFIX.$key;
        Cache::add($full, 0, self::TTL);
        Cache::increment($full, $by);
    }
}
