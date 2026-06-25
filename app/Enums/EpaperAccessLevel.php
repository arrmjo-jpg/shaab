<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * مستوى وصول العدد الرقميّ:
 *  - public: متاح للعموم (مشاهدة فقط، بلا تنزيل افتراضاً).
 *  - subscriber: للمشتركين (المضيف يربط منطق الاستحقاق)؛ العموم يرون صفحة تشويق.
 *  - private: مقيَّد (لا عرض عامّ) — 404 لغير الإداريّ.
 */
enum EpaperAccessLevel: string
{
    case Public = 'public';
    case Subscriber = 'subscriber';
    case Private = 'private';

    public function label(): string
    {
        return __('epaper.access_level.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
