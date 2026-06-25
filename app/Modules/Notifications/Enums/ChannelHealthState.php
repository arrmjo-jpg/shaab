<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * الحالة الفعّالة للقناة (effective_state) — تُعرَض في الإدارة وحدها، وتُحسَب من ثلاثة مدخلات:
 *   enabled=false                  ⇒ disabled
 *   configured=false               ⇒ unconfigured
 *   configured=true & healthy=false ⇒ degraded   (يشمل اعتمادًا مرفوضًا — التفصيل في last_error)
 *   configured=true & healthy=true  ⇒ healthy
 */
enum ChannelHealthState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
    case Unconfigured = 'unconfigured';

    /** الحالة الفعّالة من المدخلات الثلاثة. */
    public static function resolve(bool $enabled, bool $configured, bool $healthy): self
    {
        return match (true) {
            ! $enabled => self::Disabled,
            ! $configured => self::Unconfigured,
            ! $healthy => self::Degraded,
            default => self::Healthy,
        };
    }

    /** هل تصلح القناة للإرسال الآن؟ (healthy فقط). */
    public function isSendable(): bool
    {
        return $this === self::Healthy;
    }

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'سليمة',
            self::Degraded => 'متدهورة',
            self::Disabled => 'معطّلة',
            self::Unconfigured => 'غير مهيّأة',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
