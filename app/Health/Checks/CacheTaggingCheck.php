<?php

declare(strict_types=1);

namespace App\Health\Checks;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * حارس إنتاج: يتحقّق أن مخزن الكاش الفعّال يدعم الوسوم (tags).
 *
 * طبقة كاش المحتوى (المقالات/الريلز) تعتمد Cache::tags(...)؛ ومخزنا database و
 * file لا يدعمان الوسوم فتُلقي tags() استثناءً ⇒ تعطّل كل نقاط القراءة العامة.
 * redis/memcached/array مدعومة. هذا الفحص يجعل سوء الضبط مرئياً عبر نقطة
 * health المحمية وأمر health:check المجدوَل، بدل فشل صامت في الإنتاج.
 */
class CacheTaggingCheck extends Check
{
    public function run(): Result
    {
        $driver = (string) config('cache.default');
        $result = Result::make()->meta(['store' => $driver]);

        if (Cache::getStore() instanceof TaggableStore) {
            return $result->ok("Cache store [{$driver}] supports tagging.");
        }

        return $result->failed(
            "Cache store [{$driver}] does NOT support tagging — content caches require it. "
            .'Set CACHE_STORE=redis (or memcached) in production.'
        );
    }
}
