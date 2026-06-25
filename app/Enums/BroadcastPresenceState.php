<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة التحكّم التعاوني للحضور — تُسلَّم في استجابة النبضة فيتفاعل العميل تعاونياً:
 * يوقف المشغّل/يفكّ الارتباط/يتوقّف عن النبض حسب الحالة. لا قطع صارم على مستوى
 * البايت (البثّ مصدر خارجي لا يمرّ ببنيتنا) — قويّ للمُصادَقين، أفضل-جهد للزوّار.
 *
 * allowed وحدها تُحتسَب ضمن «المشاهدون الآن»؛ بقية الحالات خروجٌ تعاونيّ (لا تُحتسَب).
 */
enum BroadcastPresenceState: string
{
    case Allowed = 'allowed';
    case Closed = 'closed';            // أُغلق الجمهور (طوارئ/إشراف)
    case Kicked = 'kicked';            // طرد مؤقّت لهذا العضو
    case Banned = 'banned';            // حظر دائم لهذا العضو
    case Ended = 'ended';              // انتهى البثّ
    case Offline = 'offline';          // خارج البثّ مؤقّتاً
    case Unavailable = 'unavailable';  // غير متاح عموماً (مسودة/مؤرشف/محذوف)

    /** هل يُحتسَب صاحب هذه الحالة ضمن «المشاهدون الآن»؟ */
    public function isPresent(): bool
    {
        return $this === self::Allowed;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
