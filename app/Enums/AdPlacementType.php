<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع المساحة الإعلانية (Ad Zone) — يصف موضع/سلوك العرض في الواجهة.
 * عرض القيم في الإدارة عبر ملفات اللغة (lang ads) — لا نصوص مضمّنة.
 */
enum AdPlacementType: string
{
    case Banner = 'banner';
    case Inline = 'inline';
    case Sidebar = 'sidebar';
    case Interstitial = 'interstitial';
    case Overlay = 'overlay';
    case Preroll = 'preroll';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
