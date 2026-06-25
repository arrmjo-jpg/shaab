<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع الإعلان المطلوب — اختياران فقط (لا نصّ حرّ):
 *  - image: إعلان صورة (يُرفَق ملفّ صورة).
 *  - html : إعلان HTML (يُرفَق ملفّ ZIP يحتوي ملفّات الإعلان — يُحفَظ كمرفق خامّ للمراجعة فقط،
 *           بلا فكّ ضغط/تحليل/تنفيذ/عرض).
 */
enum AdType: string
{
    case Image = 'image';
    case Html = 'html';

    public function label(): string
    {
        return __('ad_request.ad_type.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
