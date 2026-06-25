<?php

declare(strict_types=1);

namespace App\Support\Engagement;

use App\Models\EngagementCounter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;

/**
 * مخزن مؤقّت لزيادات المشاهدات — يزيل تنازع الصفّ الساخن تحت الانتشار الفيروسي.
 *
 * المشكلة: «UPDATE counters SET views=views+1 WHERE id=X» لكل مشاهدة يُسلسِل كل
 * قرّاء الفيديو X على قفل صفّه. الحل: تُجمَّع الزيادات في الكاش (Redis) عبر زيادة
 * ذرّية لكل هدف (بلا قفل صفّ)، ثم تُفرَّغ دورياً (engagement:flush-views) في
 * UPDATE واحد لكل هدف يحمل الدلتا المتراكمة — يدمج N مشاهدة في كتابة واحدة.
 *
 * فهرس «المتّسخ»: يُسجَّل الهدف مرّة واحدة فقط عند أوّل مشاهدة في النافذة (الزيادة
 * تُعيد 1)، تحت قفل قصير نادر الالتقاط — فلا تنازع لكل مشاهدة على الفهرس. التفريغ
 * يقرأ الفهرس ويصفّره ذرّياً ثم يسحب دلتا كل هدف. فقدان طفيف محتمل عند انهيار الكاش
 * مقبول لتحليلات تقريبية. يتطلّب مخزناً يدعم الأقفال (Redis إنتاجاً، array اختباراً).
 */
final class ViewBuffer
{
    private const DELTA_PREFIX = 'engbuf:delta:';

    private const DIRTY_INDEX = 'engbuf:dirty:index';

    private const DIRTY_LOCK = 'engbuf:dirty:lock';

    /** المخزن يجب أن يدعم الأقفال (LockProvider) — redis/memcached/array/dynamodb. */
    public static function supported(): bool
    {
        return Cache::getStore() instanceof LockProvider;
    }

    /** يسجّل مشاهدة في المخزن المؤقّت (زيادة ذرّية + تسجيل أوّل ظهور في الفهرس). */
    public static function add(string $type, int $id, string $channel = 'direct'): void
    {
        $n = (int) Cache::increment(self::deltaKey($type, $id, $channel));

        // أوّل مشاهدة لهذا الهدف+القناة في النافذة الحالية ⇒ سجّله للتفريغ (نادر، تحت قفل).
        if ($n === 1) {
            Cache::lock(self::DIRTY_LOCK, 5)->block(3, function () use ($type, $id, $channel): void {
                $index = Cache::get(self::DIRTY_INDEX, []);
                $index[self::member($type, $id, $channel)] = true;
                Cache::forever(self::DIRTY_INDEX, $index);
            });
        }
    }

    /**
     * يفرّغ كل الدلتا المتراكمة إلى عدّادات قاعدة البيانات (UPDATE واحد لكل هدف).
     * يُعيد عدد الأهداف المُفرَّغة. آمن عند الفراغ أو عدم الدعم (no-op).
     */
    public static function flush(): int
    {
        if (! self::supported()) {
            return 0;
        }

        // اقرأ الفهرس وصفّره ذرّياً — مشاهدات لاحقة تعيد تسجيل الهدف للنافذة التالية.
        $members = [];
        Cache::lock(self::DIRTY_LOCK, 10)->block(5, function () use (&$members): void {
            $members = array_keys(Cache::get(self::DIRTY_INDEX, []));
            Cache::forget(self::DIRTY_INDEX);
        });

        // اجمع الدلتا حسب الهدف (للعدّاد) وحسب الهدف+القناة (للتجميع اليوميّ).
        /** @var array<string,array{type:string,id:int,total:int,channels:array<string,int>}> $targets */
        $targets = [];
        foreach ($members as $member) {
            [$type, $id, $channel] = self::parse($member);
            $delta = (int) Cache::pull(self::deltaKey($type, $id, $channel), 0);
            if ($delta <= 0) {
                continue;
            }
            $tk = $type.'|'.$id;
            $targets[$tk] ??= ['type' => $type, 'id' => $id, 'total' => 0, 'channels' => []];
            $targets[$tk]['total'] += $delta;
            $targets[$tk]['channels'][$channel] = ($targets[$tk]['channels'][$channel] ?? 0) + $delta;
        }

        $flushed = 0;
        foreach ($targets as $t) {
            self::applyDelta($t['type'], $t['id'], $t['total']);                              // عدّاد المشاهدات
            DailyEngagementRollup::addViews($t['type'], $t['id'], $t['total'], $t['channels']); // تجميع يوميّ (مع القناة)
            $flushed++;
        }

        return $flushed;
    }

    /** يطبّق الدلتا على العدّاد (الصفّ الموجود مباشرةً، وإلا يُنشأ أوّلاً). */
    private static function applyDelta(string $type, int $id, int $delta): void
    {
        $affected = EngagementCounter::query()
            ->where('engageable_type', $type)
            ->where('engageable_id', $id)
            ->increment('views', $delta);

        if ($affected === 0) {
            EngagementCounter::query()
                ->firstOrCreate(['engageable_type' => $type, 'engageable_id' => $id])
                ->increment('views', $delta);
        }
    }

    private static function deltaKey(string $type, int $id, string $channel): string
    {
        return self::DELTA_PREFIX.$type.':'.$id.':'.$channel;
    }

    /** عضو الفهرس: نوع|مُعرّف|قناة («|» لا يظهر في أسماء الأصناف/أسماء morph المستعارة). */
    private static function member(string $type, int $id, string $channel): string
    {
        return $type.'|'.$id.'|'.$channel;
    }

    /** @return array{0:string,1:int,2:string} */
    private static function parse(string $member): array
    {
        $parts = explode('|', $member, 3);

        return [$parts[0], (int) ($parts[1] ?? 0), $parts[2] ?? 'direct'];
    }
}
