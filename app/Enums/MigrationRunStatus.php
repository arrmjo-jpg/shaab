<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة دورة حياة تشغيلة ترحيل ووردبريس.
 * draft → ready (اتصال/تدقيق/خرائط/معاينة) → running ⇄ paused → completed/failed.
 * مهام الطابور تقرأ هذه الحالة للتحكّم (إيقاف مؤقّت/إيقاف آمن).
 */
enum MigrationRunStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Running = 'running';
    case Paused = 'paused';
    case Stopping = 'stopping';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('wp_migration.run_status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** التشغيلة في طور تنفيذي فعّال (تمنع تعديل الإعدادات/الخرائط). */
    public function isActive(): bool
    {
        return in_array($this, [self::Running, self::Paused, self::Stopping], true);
    }

    /** التشغيلة وصلت حالة نهائية. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
