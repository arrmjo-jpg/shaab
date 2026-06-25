<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Support\Cache\BroadcastCacheTags;
use App\Support\Cache\CachedRead;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * محرّك الحضور التقريبي — الكاش (Redis إنتاجاً) فقط، لا كتابة قاعدة بيانات لكل نبضة.
 *
 * النموذج (يتوسّع حتى 100k على بثّ واحد، بأسلوب ViewBuffer — مساعد ساكن + تجريد الكاش):
 *   • النافذة الزمنية تُقسَّم دلاءً بطول النبضة (heartbeat_interval).
 *   • أوّل نبضة لعضو في الدلو الحالي تزيد عدّاد الدلو ذرّياً (Cache::add كمزيل تكرار)؛
 *     تكرار نبضات نفس العضو لا يضخّم العدّ (منع التكرار + إعادة الاتصال آمنان).
 *   • «المشاهدون الآن» ≈ أكبر عدّ بين الدلو السابق المكتمل والدلو الحالي (تنعيم بلا
 *     نقصٍ عند بداية الدلو). تقريبيّ عمداً (الحبيبة = النبضة) — المنتج يطلب تقريباً.
 *   • كل المفاتيح بعمر TTL (دلاء + مزيلات تكرار) ⇒ ذاكرة محدودة وتنظيف تلقائي؛ بثّ
 *     بارد يحرّر مفاتيحه تلقائياً.
 *
 * المقايضة الصريحة (دقّة مقابل تَوسّع): العدّ بحبيبة الدلو، والتمييز بمفاتيح SETNX لكل
 * عضو/دلو (ذاكرة O(المشاهدين النشطين) لكل دلو، محدودة بـ TTL). لأحجام فلكية (>~500k)
 * يُستبدَل منطق العدّ بـ Redis HyperLogLog (PFADD/PFCOUNT) دون تغيير العقد العام.
 */
final class BroadcastPresence
{
    private const SEEN = 'bpres:seen:';

    private const COUNT = 'bpres:cnt:';

    private const SNAPSHOT = 'bpres:snap:';

    /** يتطلّب مخزناً يدعم العمليات الذرّية/الأقفال (redis إنتاجاً، array اختباراً). */
    public static function supported(): bool
    {
        return Cache::getStore() instanceof LockProvider;
    }

    public static function interval(): int
    {
        return max(5, (int) config('broadcast.presence.heartbeat_interval', 40));
    }

    private static function bucket(): int
    {
        return intdiv(now()->getTimestamp(), self::interval());
    }

    /**
     * يسجّل نبضة عضو نشط على بثّ — يُحتسَب مرّة واحدة لكل دلو (منع تكرار ذرّي).
     * آمن عند عدم دعم المخزن (no-op).
     */
    public static function touch(int $broadcastId, string $member): void
    {
        if (! self::supported()) {
            return;
        }

        $bucket = self::bucket();
        $ttl = self::interval() * 3;

        // أوّل ظهور للعضو في هذا الدلو ⇒ زِد عدّاد الدلو (ذرّي، بلا تنازع لكل نبضة).
        if (Cache::add(self::SEEN.$broadcastId.':'.$bucket.':'.$member, 1, $ttl)) {
            $key = self::COUNT.$broadcastId.':'.$bucket;
            Cache::add($key, 0, $ttl);  // ثبّت المفتاح وعمره مرّة واحدة قبل الزيادة
            Cache::increment($key);
        }
    }

    /** «المشاهدون الآن» التقريبي — أكبر عدّ بين الدلو السابق والحالي. */
    public static function count(int $broadcastId): int
    {
        $current = self::bucket();
        $prev = (int) Cache::get(self::COUNT.$broadcastId.':'.($current - 1), 0);
        $now = (int) Cache::get(self::COUNT.$broadcastId.':'.$current, 0);

        return max($prev, $now);
    }

    /**
     * العدّ المعروض على السطح العام: «المشاهدون الآن» للبثّ المباشر/المجدول فقط
     * (مشاهدة فعلية أو انتظار)، وإلا 0 — فبثّ منتهٍ/متوقّف/فاشل لا «مشاهدين الآن».
     */
    public static function viewersNow(string $status, int $broadcastId): int
    {
        return in_array($status, [BroadcastStatus::Live->value, BroadcastStatus::Scheduled->value], true)
            ? self::count($broadcastId)
            : 0;
    }

    /**
     * تفكيك الحضور تعاونياً (إغلاق/طوارئ): يصفّر عدّادَي الدلو الحالي والسابق فورًا
     * فيهبط «المشاهدون الآن» إلى صفر دون انتظار دورة الدلو. مزيلات التكرار لكل عضو
     * تنتهي بعمرها؛ والعملاء يفكّون ارتباطهم تعاونياً عبر حالة النبضة (closed/offline).
     */
    public static function reset(int $broadcastId): void
    {
        $current = self::bucket();
        Cache::forget(self::COUNT.$broadcastId.':'.$current);
        Cache::forget(self::COUNT.$broadcastId.':'.($current - 1));
    }

    /**
     * لقطة حالة بثّ قصيرة العمر (تمنع ضرب قاعدة البيانات لكل نبضة). single-flight عبر
     * CachedRead ضدّ العاصفة عند انتهاء الصلاحية. تقادمٌ محدود (count_cache_ttl) كافٍ
     * للنموذج التعاوني — لا حاجة لإبطال من تحوّلات B2. يعيد null إن لم يوجد البثّ.
     *
     * @return array{id:int,status:string,is_public:bool,kind:string,slug:string}|null
     */
    public static function snapshot(int $broadcastId): ?array
    {
        return CachedRead::remember(
            [BroadcastCacheTags::ALL, 'bpres:bcast:'.$broadcastId],
            self::SNAPSHOT.$broadcastId,
            max(1, (int) config('broadcast.presence.count_cache_ttl', 15)),
            function () use ($broadcastId): ?array {
                $broadcast = Broadcast::query()->whereKey($broadcastId)
                    ->first(['id', 'status', 'is_public', 'kind', 'slug']);

                if ($broadcast === null) {
                    return null;
                }

                return [
                    'id' => (int) $broadcast->id,
                    'status' => $broadcast->status->value,
                    'is_public' => (bool) $broadcast->is_public,
                    'kind' => $broadcast->kind->value,
                    'slug' => (string) $broadcast->slug,
                ];
            }
        );
    }
}
