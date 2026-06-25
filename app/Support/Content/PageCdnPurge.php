<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Page;
use App\Modules\CDN\Jobs\ProcessCdnPurgeBatch;
use App\Modules\CDN\Services\CloudflareClient;
use App\Modules\CDN\Support\CdnPurgeBuffer;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;

/**
 * إبطال حافة الـ CDN عند كتابة صفحة ثابتة (إنشاء/تحديث/نشر/أرشفة/استرجاع/حذف).
 *
 * مرآةُ ReelCdnPurge/ArticleCdnPurge: يكمّل الإبطال الحبيبي لكاش التطبيق (PageCacheTags)
 * بإبطال نشِط لحافة الـ CDN. لا يحجب الكتابة: يُجمَّع في الـ buffer ويُنفَّذ عبر الطابور
 * (ProcessCdnPurgeBatch) — fire-and-forget. بوابة مزدوجة (CDN مفعّل + cdn_auto_purge).
 *
 * كما يُخطِر واجهة Next بإبطال وسوم الكاش (FrontendRevalidate) — مستقلّ عن إعداد الـ CDN
 * (بوابته الخاصّة عبر env) ومُجدوَل ومعزول الفشل. تُترجَم الوسوم عبر FrontendCacheTags::page.
 */
final class PageCdnPurge
{
    public static function purge(Page $page, ?string $oldPath = null): void
    {
        // المرحلة 7 (عزل الفشل): أيّ استثناء يُسجَّل عبر report() ولا يكسر استجابة حفظ المحتوى.
        rescue(static fn () => self::doPurge($page, $oldPath));
    }

    private static function doPurge(Page $page, ?string $oldPath = null): void
    {
        // إخطار واجهة Next بإبطال الوسوم — مستقلّ عن بوابة الـ CDN أدناه؛ مُجدوَل ومعزول الفشل.
        // السلَغ القديم من oldPath (canonical = /{locale}/pages/{slug}) ليُبطَل وسمه أيضاً.
        $oldSlug = $oldPath !== null ? basename($oldPath) : null;
        FrontendRevalidate::tags(FrontendCacheTags::page($page, $oldSlug));

        $client = new CloudflareClient;

        if (! $client->enabled() || ! $client->settings()->cdn_auto_purge) {
            return;
        }

        $urls = self::urlsFor($page);

        // عند تغيّر الـ slug/اللغة: أبطِل صفحة الـ canonical القديمة كذلك.
        if ($oldPath !== null && $oldPath !== $page->canonicalPath()) {
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
     * مجموعة روابط الإبطال لصفحة في لغتها (نقطة الـ API العامة + الصفحة + قائمة الصفحات).
     *
     * @return array<int,string>
     */
    private static function urlsFor(Page $page): array
    {
        $locale = $page->locale;
        $slug = (string) $page->slug;
        $apiBase = 'api/v1/'.$locale.'/pages';

        return [
            // نقاط الـ API العامة — حاملة ترويسات s-maxage على الحافة.
            PublicSeoBuilder::absoluteUrl($apiBase),
            PublicSeoBuilder::absoluteUrl($apiBase.'/'.$slug),
            // صفحة الواجهة العامة (SSR) — نفس النطاق (الربط الفعليّ مؤجّل لمرحلة الواجهة).
            PublicSeoBuilder::absoluteUrl($page->canonicalPath()),
        ];
    }
}
