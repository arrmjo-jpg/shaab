<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Models\Article;
use App\Support\Content\SlugGenerator;

/**
 * توليد slug حتميّ للمقال المُرحَّل (قاعدة #3/#6). يفضّل slug المصدر، ويعالج
 * التصادم بلاحقة هوية المصدر المستقرّة (wp_post_id) — نتيجة ثابتة عبر إعادات
 * التشغيل. الفرادة per-locale (يشمل المحذوف ناعماً، مطابقةً لإعداد Sluggable).
 *
 * يُضبط صراحةً على المقال قبل الحفظ؛ Sluggable لا يولّد فوق slug غير فارغ.
 */
final class MigrationSlug
{
    public static function make(
        string $sourceSlug,
        string $title,
        string $locale,
        int $wpPostId,
        ?int $currentArticleId = null,
    ): string {
        $base = SlugGenerator::makeWithFallback($sourceSlug !== '' ? $sourceSlug : $title);

        $collides = Article::withTrashed()
            ->where('locale', $locale)
            ->where('slug', $base)
            ->when($currentArticleId !== null, fn ($q) => $q->where('id', '!=', $currentArticleId))
            ->exists();

        return $collides ? $base.'-'.$wpPostId : $base;
    }
}
