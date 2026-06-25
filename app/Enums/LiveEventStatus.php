<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الحدث المباشر (للمقالات من نوع live) — مستقلّة عن حالة النشر التحريرية.
 *
 * - scheduled : مجدوَل، لم يبدأ البثّ بعد
 * - live      : مباشر الآن
 * - paused    : متوقّف مؤقتاً
 * - completed : انتهى الحدث
 *
 * تتحكّم بعرض «مباشر» على الواجهة العامة وسلوك التحديث التلقائي.
 */
enum LiveEventStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Paused = 'paused';
    case Completed = 'completed';

    public function label(): string
    {
        return __('article.event_status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
