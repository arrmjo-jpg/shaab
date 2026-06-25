<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة عنصر محتوى مُرحَّل (منشور ووردبريس → مقال).
 * partial = نجح بعض الخطوات (مثلاً المحتوى دون كل الوسائط) — يُعاد إكماله عند الاستئناف.
 */
enum MigrationItemStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Partial = 'partial';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('wp_migration.item_status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** حالات يُعاد توزيعها عند الاستئناف (لا تكرار للعمل المكتمل). */
    public function isResumable(): bool
    {
        return in_array($this, [self::Pending, self::Queued, self::Partial, self::Failed], true);
    }
}
