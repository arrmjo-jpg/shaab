<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * مزوّدو الفيديو الخارجي المدعومون (allow-list) — Wave 2.
 *
 * الفيديو الخارجي أصل مكتبة (media_assets, kind=external) لا نموذج منفصل.
 */
enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case TikTok = 'tiktok';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case X = 'x';
    case Mp4 = 'mp4';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $p): string => $p->value, self::cases());
    }
}
