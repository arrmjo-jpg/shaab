<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة عنصر وسائط مُرحَّل (مرفق ووردبريس → MediaAsset).
 * إعادة الاستخدام (dedup عبر checksum) لا تُنشئ صفاً جديداً — تُربط بالأصل الموجود.
 */
enum MigrationMediaStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('wp_migration.media_status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
