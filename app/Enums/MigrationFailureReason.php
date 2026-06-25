<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * أسباب فشل مُهيكَلة لعناصر الترحيل (قاعدة #9 — لا أخطاء عامّة). تُسجَّل في
 * MigrationItem (last_step/last_error/flags) لتصنيف الفشل في لوحة الفحص لاحقاً.
 */
enum MigrationFailureReason: string
{
    case AuthorMissing = 'author_missing';
    case SourceReadFailed = 'source_read_failed';
    case CategoryUnresolved = 'category_unresolved';
    case CategoryTypeConflict = 'category_type_conflict';
    case TransformFailed = 'transform_failed';
    case MediaUnresolved = 'media_unresolved';
    case MediaTooLarge = 'media_too_large';
    case MediaUnsupportedMime = 'media_unsupported_mime';
    case MediaSsrfBlocked = 'media_ssrf_blocked';
    case MediaNetworkError = 'media_network_error';
    case PersistFailed = 'persist_failed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return __('wp_migration.failure.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
