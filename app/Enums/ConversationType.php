<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع المحادثة: غرفة عامة واحدة للنظام · مباشرة (1↔1) · مجموعة.
 * بنية موحّدة عبر الأنواع الثلاثة (conversations + participants).
 */
enum ConversationType: string
{
    case General = 'general';
    case Direct = 'direct';
    case Group = 'group';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
