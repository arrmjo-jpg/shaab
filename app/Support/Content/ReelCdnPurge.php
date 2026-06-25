<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Reel;
use App\Modules\CDN\Jobs\ProcessCdnPurgeBatch;
use App\Modules\CDN\Services\CloudflareClient;
use App\Modules\CDN\Support\CdnPurgeBuffer;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Seo\SearchEngineNotify;

/**
 * إبطال حافة الـ CDN عند كتابة محتوى ريل (إنشاء/تحديث/نشر/أرشفة/استرجاع/حذف).
 *
 * يكمّل الإبطال الحبيبي لكاش التطبيق (ReelCacheTags) بإبطال نشِط لحافة الـ CDN كي
 * يَظهر المحتوى العام فوراً دون انتظار s-maxage. لا يحجب الكتابة ولا يُفشِلها:
 * يُجمَّع الإبطال في الـ buffer ويُنفَّذ عبر الطابور (ProcessCdnPurgeBatch) —
 * نفس آلية وحدة الـ CDN (fire-and-forget).
 *
 * بوابة مزدوجة: يُفعَّل فقط حين يكون الـ CDN مفعَّلاً ومهيَّأً (enabled) و علم
 * الإبطال التلقائي (cdn_auto_purge) مُشغَّلاً. no-op آمن خلاف ذلك — صفر نداءات.
 *
 * تغطية الإبطال (لغة الريل فقط — الواجهة العامة على نفس نطاق التطبيق):
 *   • صفحات الواجهة (SSR): canonical للريل + /{locale}/reels + .../featured +
 *     .../trending + الصفحة الرئيسية /{locale}.
 *   • نقاط الـ API العامة (هي حاملة ترويسات الكاش على الحافة): قائمة + مميَّز +
 *     رائج + تفاصيل بالـ slug تحت /api/v1/{locale}/reels.
 *   • عند تغيّر الـ slug/اللغة: صفحة الـ canonical القديمة أيضاً (منع بقايا قديمة).
 *
 * قيد معلوم (إبطال بالـ URL فقط، لا cache-tag بعد): صفحات الترقيم (?page=N)
 * وتنويعات per_page لا تُغطّى صراحةً — تُحدَّث عبر s-maxage/SWR. تُرقَّى لاحقاً
 * عبر purge-by-tag إن لزم. يُعاد استخدام PublicSeoBuilder كمصدر وحيد للروابط.
 */
final class ReelCdnPurge
{
    public static function purge(Reel $reel, ?string $oldPath = null): void
    {
        // المرحلة 7 (عزل الفشل): أيّ استثناء يُسجَّل عبر report() ولا يكسر استجابة حفظ المحتوى.
        rescue(static fn () => self::doPurge($reel, $oldPath));
    }

    private static function doPurge(Reel $reel, ?string $oldPath = null): void
    {
        // إخطار واجهة Next بإبطال الوسوم — مستقلّ عن إعداد الـ CDN أدناه؛ مُجدوَل ومعزول الفشل.
        // السلَغ القديم من oldPath (canonical = /{locale}/reels/{id}-{slug}) ليُبطَل وسمه أيضاً.
        $oldSlug = $oldPath !== null ? preg_replace('/^\d+-/', '', basename($oldPath)) : null;
        FrontendRevalidate::tags(FrontendCacheTags::reel($reel, $oldSlug));

        // إخطار محركات البحث بتحديث الخريطة عند تغيّر ريل منشور (بوابته SEARCH_PING_ENABLED).
        if ($reel->status->value === 'published') {
            SearchEngineNotify::sitemaps();
        }

        $client = new CloudflareClient;

        if (! $client->enabled() || ! $client->settings()->cdn_auto_purge) {
            return;
        }

        $urls = self::urlsFor($reel);

        // عند تغيّر الـ slug/اللغة: أبطِل صفحة الـ canonical القديمة كذلك.
        if ($oldPath !== null && $oldPath !== $reel->canonicalPath()) {
            $urls[] = PublicSeoBuilder::absoluteUrl($oldPath);
        }

        $urls = array_values(array_filter(array_unique($urls)));
        if ($urls === []) {
            return;
        }

        (new CdnPurgeBuffer)->add($urls);
        ProcessCdnPurgeBatch::dispatch()->afterCommit();
    }

    /**
     * مجموعة روابط الإبطال لريل في لغته (صفحات الواجهة + نقاط الـ API).
     *
     * @return array<int,string>
     */
    private static function urlsFor(Reel $reel): array
    {
        $locale = $reel->locale;
        $slug = (string) $reel->slug;
        $apiBase = 'api/v1/'.$locale.'/reels';

        return [
            // صفحات الواجهة العامة (SSR) — نفس النطاق.
            PublicSeoBuilder::absoluteUrl($reel->canonicalPath()),
            PublicSeoBuilder::absoluteUrl($locale.'/reels'),
            PublicSeoBuilder::absoluteUrl($locale.'/reels/featured'),
            PublicSeoBuilder::absoluteUrl($locale.'/reels/trending'),
            PublicSeoBuilder::absoluteUrl($locale),
            // نقاط الـ API العامة — حاملة ترويسات s-maxage على الحافة.
            PublicSeoBuilder::absoluteUrl($apiBase),
            PublicSeoBuilder::absoluteUrl($apiBase.'/featured'),
            PublicSeoBuilder::absoluteUrl($apiBase.'/trending'),
            PublicSeoBuilder::absoluteUrl($apiBase.'/'.$slug),
        ];
    }
}
