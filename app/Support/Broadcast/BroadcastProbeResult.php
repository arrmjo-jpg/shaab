<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

/**
 * نتيجة فحص صحّة مصدر بثّ — كائن قيمة ثابت. probeable=false للمصادر غير القابلة
 * للفحص خادمياً (يوتيوب/مزوّد خارجي) — لا تُعدّ فشلاً.
 */
final class BroadcastProbeResult
{
    private function __construct(
        public readonly bool $probeable,
        public readonly bool $healthy,
        public readonly ?int $latencyMs,
        public readonly ?string $reason,
    ) {}

    public static function healthy(?int $latencyMs = null): self
    {
        return new self(true, true, $latencyMs, null);
    }

    public static function failed(string $reason, ?int $latencyMs = null): self
    {
        return new self(true, false, $latencyMs, $reason);
    }

    public static function notProbeable(): self
    {
        return new self(false, false, null, null);
    }
}
