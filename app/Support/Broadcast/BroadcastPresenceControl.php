<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Enums\BroadcastPresenceState;
use App\Enums\BroadcastStatus;
use Illuminate\Support\Facades\Cache;

/**
 * حالة التحكّم التعاوني للحضور — B5 يوفّر التخزين والقراءة والحسم؛ سطح الإشراف
 * (نقاط الإدارة + RBAC + التدقيق) يُبنى فوقها في B6.
 *
 *   close/reopen → إغلاق الجمهور على مستوى البثّ (طوارئ).
 *   ban/unban    → حظر دائم لعضو (هوية).
 *   kick         → طرد مؤقّت لعضو (TTL ثم يُسمح بالعودة).
 *
 * التطبيق تعاونيّ: النبضة تُعيد الحالة، فيفكّ العميل ارتباطه ويتوقّف عن الاحتساب —
 * لا قطع صارم على مستوى البايت (البثّ خارجي لا يمرّ ببنيتنا).
 */
final class BroadcastPresenceControl
{
    private const CLOSED = 'bpres:ctl:closed:';

    private const BAN = 'bpres:ctl:ban:';

    private const KICK = 'bpres:ctl:kick:';

    public static function close(int $broadcastId): void
    {
        Cache::forever(self::CLOSED.$broadcastId, true);
    }

    public static function reopen(int $broadcastId): void
    {
        Cache::forget(self::CLOSED.$broadcastId);
    }

    public static function isClosed(int $broadcastId): bool
    {
        return (bool) Cache::get(self::CLOSED.$broadcastId, false);
    }

    /**
     * حظر مؤقّت لعضو — عمره (TTL) هو مصدر الانتهاء التلقائي (لا مهمّة تنظيف). الحمولة
     * تحمل السبب/الفاعل/زمن الانتهاء للعرض. قيمة غير فارغة دائماً (truthy لـ isBanned).
     *
     * @param  array<string,mixed>  $meta
     */
    public static function ban(int $broadcastId, string $member, ?int $ttlSeconds = null, array $meta = []): void
    {
        $ttl = $ttlSeconds ?? (max(1, (int) config('broadcast.presence.default_ban_minutes', 60)) * 60);

        Cache::put(self::BAN.$broadcastId.':'.$member, $meta !== [] ? $meta : ['banned' => true], $ttl);
    }

    public static function unban(int $broadcastId, string $member): void
    {
        Cache::forget(self::BAN.$broadcastId.':'.$member);
    }

    public static function isBanned(int $broadcastId, string $member): bool
    {
        return Cache::has(self::BAN.$broadcastId.':'.$member);
    }

    /**
     * تفاصيل الحظر النشِط لعضو (السبب/الفاعل/الانتهاء) أو null إن لم يكن محظوراً.
     *
     * @return array<string,mixed>|null
     */
    public static function banInfo(int $broadcastId, string $member): ?array
    {
        $value = Cache::get(self::BAN.$broadcastId.':'.$member);

        return is_array($value) ? $value : null;
    }

    public static function kick(int $broadcastId, string $member, ?int $ttl = null): void
    {
        Cache::put(
            self::KICK.$broadcastId.':'.$member,
            true,
            $ttl ?? max(30, (int) config('broadcast.presence.kick_ttl', 300))
        );
    }

    public static function isKicked(int $broadcastId, string $member): bool
    {
        return (bool) Cache::get(self::KICK.$broadcastId.':'.$member, false);
    }

    /**
     * يحسم حالة التحكّم لعضو على بثّ من (حالة البثّ + الأعلام). الترتيب: عدم التوفّر
     * أولاً (مسودة/مؤرشف/غير عام)، ثم حالة البثّ (ended/offline)، ثم الإشراف
     * (closed → banned → kicked)، وإلا allowed.
     */
    /** عام ومرئي للحضور = عام + ليس مسودّة/مؤرشفاً (يطابق scopePubliclyVisible). */
    public static function isPubliclyVisible(string $status, bool $isPublic): bool
    {
        return $isPublic
            && $status !== BroadcastStatus::Draft->value
            && $status !== BroadcastStatus::Archived->value;
    }

    public static function resolve(string $status, bool $isPublic, int $broadcastId, string $member): BroadcastPresenceState
    {
        if (! self::isPubliclyVisible($status, $isPublic)) {
            return BroadcastPresenceState::Unavailable;
        }

        if ($status === BroadcastStatus::Ended->value) {
            return BroadcastPresenceState::Ended;
        }
        if ($status === BroadcastStatus::Offline->value) {
            return BroadcastPresenceState::Offline;
        }
        if (self::isClosed($broadcastId)) {
            return BroadcastPresenceState::Closed;
        }
        if (self::isBanned($broadcastId, $member)) {
            return BroadcastPresenceState::Banned;
        }
        if (self::isKicked($broadcastId, $member)) {
            return BroadcastPresenceState::Kicked;
        }

        return BroadcastPresenceState::Allowed;
    }
}
