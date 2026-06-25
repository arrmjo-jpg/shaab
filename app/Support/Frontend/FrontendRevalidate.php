<?php

declare(strict_types=1);

namespace App\Support\Frontend;

use App\Jobs\RevalidateFrontendCacheJob;

/**
 * واجهة موحّدة لإخطار واجهة Next العامة بإبطال وسوم الكاش عند كتابة محتوى.
 *
 * بوابة واحدة: تُفعَّل فقط حين يكون FRONTEND_REVALIDATE_URL + secret مضبوطَين (no-op آمن خلاف
 * ذلك — صفر نداءات). الإبطال مُجدوَل ومعزول الفشل عبر RevalidateFrontendCacheJob فلا يحجب
 * الكتابة ولا يكسرها — مرآةُ نمط ArticleCdnPurge/ReelCdnPurge (fire-and-forget).
 */
final class FrontendRevalidate
{
    /** @param array<int,string> $tags وسوم كاش الواجهة (انظر FrontendCacheTags). */
    public static function tags(array $tags): void
    {
        $url = (string) config('services.frontend_revalidate.url', '');
        $secret = (string) config('services.frontend_revalidate.secret', '');
        $tags = array_values(array_unique(array_filter($tags)));

        if ($url === '' || $secret === '' || $tags === []) {
            return; // غير مُهيّأ ⇒ لا عملية
        }

        // afterCommit: داخل معاملة ⇒ يُرسَل بعد نجاحها فقط (لا إبطال يسبق بيانات لم تُلتزم)؛
        // خارجها ⇒ إرسال فوريّ كالمعتاد.
        RevalidateFrontendCacheJob::dispatch($tags)->afterCommit();
    }
}
