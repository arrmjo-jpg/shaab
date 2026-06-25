<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Jobs\PingSearchEnginesJob;
use App\Support\Content\PublicSeoBuilder;

/**
 * واجهة موحّدة لإخطار محركات البحث (Google/Bing) بتحديث الخريطة عند نشر/تغيّر محتوى منشور.
 *
 * بوابة واحدة: تُفعَّل فقط حين SEARCH_PING_ENABLED=true (no-op آمن خلاف ذلك — صفر نداءات).
 * الإخطار مُجدوَل ومعزول الفشل عبر PingSearchEnginesJob فلا يحجب الكتابة — مرآةُ نمط
 * FrontendRevalidate (نفس أسلوب «notify-on-write» في مسار النشر).
 */
final class SearchEngineNotify
{
    /** يُخطِر المحركات بخريطة الفهرس (sitemap.xml). */
    public static function sitemaps(): void
    {
        if (! (bool) config('services.search_ping.enabled', false)) {
            return; // غير مُفعَّل ⇒ لا عملية
        }

        $sitemapUrl = PublicSeoBuilder::absoluteUrl(route('sitemap.index', [], false));

        // afterCommit: لا إخطار محرّكات قبل التزام الكتابة (خارج المعاملة = فوريّ كالمعتاد).
        PingSearchEnginesJob::dispatch($sitemapUrl)->afterCommit();
    }
}
