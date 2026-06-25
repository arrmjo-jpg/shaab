<?php

declare(strict_types=1);

namespace App\Actions\Admin\System;

use App\Support\Cache\ArticleCacheTags;
use App\Support\Cache\ReelCacheTags;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * تفريغ كاش المحتوى العام (المقالات + الريلز + خرائط الموقع + التصنيفات) — تحكّم
 * تشغيلي للاسترداد عند الاشتباه ببقايا قديمة. يُفرّغ مظلّات الوسوم فقط (لا يمسّ
 * كاش النظام/الجلسات/الإعدادات)، فالعملية آمنة وقابلة لإعادة البناء عند أول طلب.
 *
 * يتطلّب مخزناً يدعم الوسوم (redis إنتاجاً) — وهو إلزامي أصلاً (RedisProductionCheck).
 * يُدوَّن في سجلّ التدقيق (مَن فرّغ ومتى) — حدث تشغيلي حسّاس.
 */
class ClearContentCacheAction
{
    /** مجموعات وسوم المحتوى العام القابلة للتفريغ الآمن. */
    private const TAG_GROUPS = [
        ArticleCacheTags::ALL,      // articles: feeds + details + categories
        ArticleCacheTags::SITEMAP,  // articles:sitemap
        ReelCacheTags::ALL,         // reels: feeds + details
        'categories',               // أشجار/قوائم التصنيفات + خرائط التصنيفات
    ];

    public function handle(): JsonResponse
    {
        $cleared = [];
        foreach (self::TAG_GROUPS as $tag) {
            try {
                Cache::tags([$tag])->flush();
                $cleared[] = $tag;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        activity('system')
            ->event('cache_cleared')
            ->withProperties(['groups' => $cleared])
            ->log(__('system.cache_cleared'));

        return ApiResponse::success(
            data: ['cleared' => $cleared, 'at' => now()->toIso8601String()],
            meta: ['message' => __('system.cache_cleared')],
        );
    }
}
