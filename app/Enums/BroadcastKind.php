<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * تصنيف تحريري للبثّ يقود تقسيم المسارات العامة (/live · /tv · /radio) وتجربة
 * الواجهة — مستقلّ عن source_type التقني (قناة قد تكون HLS أو يوتيوب لايف).
 */
enum BroadcastKind: string
{
    case Live = 'live';
    case Tv = 'tv';
    case Radio = 'radio';

    public function label(): string
    {
        return __('broadcast.kind.'.$this->value);
    }

    /** قطعة المسار العام المقابلة (نفس القيمة — تُبقى صريحة للوضوح). */
    public function routeSegment(): string
    {
        return $this->value;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
