<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/** هدف الرابط العميق في حمولة الإشعار (data) — يقرؤه التطبيق فيوجّه داخليًّا. */
enum DeepLinkType: string
{
    case None = 'none';
    case Article = 'article';
    case Category = 'category';
    case Video = 'video';
    case Reel = 'reel';
    case Broadcast = 'broadcast';
    case External = 'external';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
