<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\VideoLibrary;

use App\Support\Content\VideoSeoBuilder;
use Illuminate\Http\Request;

/**
 * مورد تفاصيل الفيديو العام — بطاقة العرض الكاملة + كتلة SEO الكاملة (canonical +
 * hreflang/x-default + OG/Twitter + VideoObject). يُستخدَم حصراً على نقطة التفاصيل
 * بالـ slug (صفحة الزحف). القوائم/الخلاصات تستخدم البطاقة الخفيفة (بلا SEO/N+1).
 */
class PublicVideoResource extends PublicVideoCardResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'seo' => VideoSeoBuilder::build($this->resource),
        ]);
    }
}
