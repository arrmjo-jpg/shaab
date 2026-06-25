<?php

declare(strict_types=1);

namespace App\Modules\CDN\Support;

use App\Modules\CDN\Enums\CdnFailureType;
use Closure;

/**
 * إعادة محاولة ذكية مع backoff أسّي للأعطال العابرة فقط.
 *
 * العملية تُرجع مصفوفة: ['ok' => bool, 'failure' => ?CdnFailureType, ...]
 */
final class CdnRetry
{
    /**
     * @param  Closure(): array  $operation
     */
    public static function run(Closure $operation): array
    {
        $maxAttempts = max(1, (int) config('cdn.retry.max_attempts', 3));
        $baseMs = (int) config('cdn.retry.base_ms', 200);
        $capMs = (int) config('cdn.retry.cap_ms', 2000);

        $attempt = 0;
        $result = ['ok' => false, 'failure' => CdnFailureType::Unknown];

        while ($attempt < $maxAttempts) {
            $attempt++;
            $result = $operation();

            if (($result['ok'] ?? false) === true) {
                return $result;
            }

            $failure = $result['failure'] ?? CdnFailureType::Unknown;

            if (! $failure instanceof CdnFailureType || ! $failure->shouldRetry() || $attempt >= $maxAttempts) {
                return $result;
            }

            $this_backoff = min($capMs, $baseMs * (2 ** ($attempt - 1)));

            // لا تأخير في بيئة الاختبار للحفاظ على سرعة المجموعة
            if (! app()->environment('testing')) {
                usleep($this_backoff * 1000);
            }
        }

        return $result;
    }
}
