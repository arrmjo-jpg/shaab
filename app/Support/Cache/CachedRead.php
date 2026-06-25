<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * قراءة مُخزَّنة بحماية single-flight ضدّ عاصفة الطوابير (cache stampede).
 *
 * المشكلة: Cache::tags()->remember() القياسي يسمح لعدّة طلبات متزامنة بإعادة بناء
 * نفس المفتاح فور انتهائه/إبطاله (أخبار عاجلة/مقال فيروسي) — ضربة متزامنة على DB.
 *
 * الحل: قفل لكل مفتاح (Cache::lock) يضمن أن **طلباً واحداً فقط** يعيد البناء؛
 * البقية تنتظر القفل (block) ثم تقرأ القيمة الجاهزة. عند انتهاء مهلة الانتظار
 * (المُنتِج بطيء) نرتدّ بأمان إلى حساب مباشر دون كاش — لا deadlock ولا فساد.
 *
 * تغليف القيمة في ['v' => …] يميّز «null مُخزَّن» (نتيجة فعلية) عن «غياب» (miss)،
 * فيُخزَّن الـ 404/إعادة التوجيه ولا يُعاد حسابه في كل طلب (يمنع عاصفة على الغياب).
 *
 * يتطلّب مخزناً يدعم الأقفال (redis/array) — متوفّر إنتاجاً واختباراً.
 */
final class CachedRead
{
    /** مهلة انتظار القفل بالثواني (المنتظِرون) قبل الارتداد للحساب المباشر. */
    private const BLOCK_SECONDS = 5;

    /** أقصى عمر للقفل بالثواني (حماية ضدّ منتِج عالق). */
    private const LOCK_TTL = 15;

    /**
     * @param  array<int,string>  $tags
     */
    public static function remember(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        $store = Cache::tags($tags);

        $hit = $store->get($key);
        if (is_array($hit) && array_key_exists('v', $hit)) {
            return $hit['v'];
        }

        $lock = Cache::lock('swr:'.$key, self::LOCK_TTL);

        try {
            // طلب واحد يبني؛ الباقي ينتظر القفل ثم يقرأ القيمة الجاهزة.
            return $lock->block(self::BLOCK_SECONDS, function () use ($store, $key, $ttl, $callback): mixed {
                $again = $store->get($key);
                if (is_array($again) && array_key_exists('v', $again)) {
                    return $again['v'];
                }

                $value = $callback();
                $store->put($key, ['v' => $value], $ttl);

                return $value;
            });
        } catch (LockTimeoutException) {
            // ارتداد آمن: ربما خُزِّنت القيمة الآن، وإلا احسبها مباشرةً (بلا قفل/كاش).
            $hit = $store->get($key);
            if (is_array($hit) && array_key_exists('v', $hit)) {
                return $hit['v'];
            }

            return $callback();
        }
    }
}
