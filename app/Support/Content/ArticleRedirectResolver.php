<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Article;
use App\Models\ArticleUrlHistory;
use Illuminate\Support\Str;

/**
 * مُحلِّل إعادة التوجيه 301 للمقالات (SEO / هجرة): يربط مساراً/سلَغاً قديماً
 * بالمقال المنشور الحالي. مصدر الحقيقة: article_url_history (يلتقطه تحديث المقال
 * عند تغيّر المسار القانوني — تغيّر slug و/أو locale).
 *
 * منع الحلقات: لا يُعاد التوجيه إن كان الهدف مطابقاً للمطلوب (نفس slug/locale)،
 * أو إن لم يَعُد المقال منشوراً. البحث مفهرس على (locale, old_path).
 */
final class ArticleRedirectResolver
{
    /**
     * مطابقة مسار قانوني قديم كامل (/{locale}/articles/{id}-{slug}) — مفهرس O(1).
     * يُرجع المقال المنشور الحالي، أو null (لا تطابق/غير منشور/حلقة).
     */
    public static function resolveByPath(string $locale, string $oldPath): ?Article
    {
        if (! in_array($locale, Article::LOCALES, true)) {
            return null;
        }

        $oldPath = '/'.trim($oldPath, '/');

        $row = ArticleUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', $oldPath)
            ->latest('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $article = Article::query()->published()->whereKey($row->article_id)->first();
        if ($article === null) {
            return null;
        }

        // منع الحلقة: الهدف نفسه المسار المطلوب.
        return $article->canonicalPath() === $oldPath ? null : $article;
    }

    /**
     * مطابقة بالـ (locale قديم + slug قديم) — للاستهلاك عبر نقطة الـ API
     * (/{locale}/articles/{slug}). يُستخرَج الـ slug من old_path ويُطابَق تماماً
     * (تفادي مطابقة جزئية مثل news↔breaking-news).
     */
    public static function resolveBySlug(string $locale, string $slug): ?Article
    {
        if (! in_array($locale, Article::LOCALES, true) || $slug === '') {
            return null;
        }

        // مرشّحات منتهية بـ -{slug} ضمن لغة قديمة محدّدة (يحصر المسح بهذه اللغة).
        $rows = ArticleUrlHistory::query()
            ->where('locale', $locale)
            ->where('old_path', 'like', '%-'.addcslashes($slug, '\\%_'))
            ->latest('id')
            ->limit(20)
            ->get(['article_id', 'old_path']);

        foreach ($rows as $row) {
            if (self::slugFromPath((string) $row->old_path) !== $slug) {
                continue; // تطابق جزئي زائف
            }

            $article = Article::query()->published()->whereKey($row->article_id)->first();
            if ($article === null) {
                continue;
            }

            // منع الحلقة: نفس slug ونفس locale.
            if ($article->locale === $locale && $article->slug === $slug) {
                return null;
            }

            return $article;
        }

        return null;
    }

    /** يستخرج الـ slug من مسار قانوني (/{locale}/articles/{id}-{slug}). */
    private static function slugFromPath(string $path): string
    {
        $base = (string) Str::afterLast(trim($path, '/'), '/'); // {id}-{slug}

        return (string) preg_replace('/^\d+-/', '', $base);
    }
}
