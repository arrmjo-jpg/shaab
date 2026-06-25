<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\ChannelHealthState;

/**
 * تقرير صحّة لحظيّ يُرجِعه الدرايفر — مدخلان خامّان (configured/healthy) + تفصيل. السجلّ المركزيّ
 * يحسب منه effective_state بدمج enabled من الإعدادات. (UI يعرض effective_state فقط.)
 */
final class ChannelHealth
{
    public function __construct(
        public readonly bool $configured,
        public readonly bool $healthy,
        public readonly ?string $lastError = null,
        public readonly ?int $latencyMs = null,
    ) {}

    public static function healthy(?int $latencyMs = null): self
    {
        return new self(true, true, null, $latencyMs);
    }

    public static function unconfigured(): self
    {
        return new self(false, false, 'channel not configured');
    }

    /** مهيّأة لكنّها لا تعمل (اعتماد مرفوض/شبكة/خادم) — السبب في $error. */
    public static function problem(string $error, ?int $latencyMs = null): self
    {
        return new self(true, false, mb_substr($error, 0, 1000), $latencyMs);
    }

    /** الحالة الفعّالة بدمج enabled (من الإعدادات). */
    public function effectiveState(bool $enabled): ChannelHealthState
    {
        return ChannelHealthState::resolve($enabled, $this->configured, $this->healthy);
    }
}
