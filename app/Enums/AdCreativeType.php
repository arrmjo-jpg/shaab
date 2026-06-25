<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع الإبداع الإعلاني (Creative):
 *   - image : أصل صورة في media_assets (يُخدَم عبر CDN/R2).
 *   - html  : كود HTML مُنقّى (HTMLPurifier) يُعرَض داخل iframe معزول (SafeFrame).
 *   - video : جاهز-مستقبلاً فقط — لا تنسيق preroll/midroll في هذه المرحلة.
 */
enum AdCreativeType: string
{
    case Image = 'image';
    case Html = 'html';
    case Video = 'video';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }

    /** أنواع مفعّلة للخدمة الآن (video مؤجّل للتنسيق المستقبليّ). */
    public function isServableNow(): bool
    {
        return $this !== self::Video;
    }
}
