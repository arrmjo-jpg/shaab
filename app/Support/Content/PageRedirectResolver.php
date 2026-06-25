<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Page;
use App\Models\PageUrlHistory;

/**
 * مُحلِّل إعادة التوجيه 301 للصفحات الثابتة (مرآة ReelRedirectResolver): يربط
 * مساراً/سلَغاً قديماً بالصفحة المنشورة الحالية. منع الحلقات: لا توجيه إن كان الهدف
 * مطابقاً للمطلوب أو إن لم تَعُد الصفحة منشورة. البحث مفهرس على (locale, old_path).
 *
 * الفرق عن Reel: canonical الصفحة slug-فقط (/{locale}/pages/{slug}، بلا بادئة id-)،
 * لذا resolveBySlug يبني المسار المرشّح ويطابق تماماً (لا LIKE ولا قشط للبادئة).
 */
final class PageRedirectResolver
{
    /** مطابقة مسار قانوني قديم كامل (/{locale}/pages/{slug}) — مفهرس O(1). */
    public static function resolveByPath(string $locale, string $oldPath): ?Page
    {
        if (! in_array($locale, Page::LOCALES, true)) {
            return null;
        }

        $oldPath = '/'.trim($oldPath, '/');

        $row = PageUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $page = Page::query()->published()->whereKey($row->page_id)->first();
        if ($page === null) {
            return null;
        }

        // منع الحلقة: لا تُعِد التوجيه إن كان canonical الحالي مطابقاً للمطلوب.
        return $page->canonicalPath() === $oldPath ? null : $page;
    }

    /** مطابقة بالـ (locale + slug) — لاستهلاك نقطة /{locale}/pages/{slug} عند الـ 404. */
    public static function resolveBySlug(string $locale, string $slug): ?Page
    {
        if (! in_array($locale, Page::LOCALES, true) || $slug === '') {
            return null;
        }

        // مسار قانوني slug-فقط: نبني المرشّح ونطابق تماماً (مفهرس).
        $candidatePath = '/'.trim("{$locale}/pages/{$slug}", '/');

        return self::resolveByPath($locale, $candidatePath);
    }
}
