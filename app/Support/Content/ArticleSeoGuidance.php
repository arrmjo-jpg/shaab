<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Article;
use App\Models\MediaAsset;

/**
 * إرشاد SEO تحريري حقيقي (لا تقييم وهمي): فحوص ملموسة على المقال يُرجِع كلٌّ منها
 * مفتاحاً + خطورة (ok/info/warn) + رسالة قابلة للترجمة عبر الواجهة. تُعرَض داخل
 * المحرّر لتوجيه المحرّر — كلّ فحص مبنيّ على بيانات فعلية، لا أرقام تجميلية.
 */
final class ArticleSeoGuidance
{
    private const TITLE_MAX = 60;       // طول عنوان SERP المثالي

    private const TITLE_MIN = 15;

    private const DESC_MIN = 50;

    private const DESC_MAX = 160;       // مقتطف وصف SERP

    /**
     * @return array<int,array{key:string,severity:string,detail:array<string,mixed>}>
     */
    public static function for(Article $article): array
    {
        $checks = [];

        $title = (string) ($article->seo_title !== null && $article->seo_title !== ''
            ? $article->seo_title
            : $article->title);
        $titleLen = mb_strlen($title);
        if ($titleLen > self::TITLE_MAX) {
            $checks[] = self::warn('title_too_long', ['length' => $titleLen, 'max' => self::TITLE_MAX]);
        } elseif ($titleLen < self::TITLE_MIN) {
            $checks[] = self::warn('title_too_short', ['length' => $titleLen, 'min' => self::TITLE_MIN]);
        } else {
            $checks[] = self::ok('title_ok');
        }

        $description = (string) ($article->seo_description !== null && $article->seo_description !== ''
            ? $article->seo_description
            : $article->excerpt);
        $descLen = mb_strlen($description);
        if ($descLen === 0) {
            $checks[] = self::warn('description_missing');
        } elseif ($descLen > self::DESC_MAX) {
            $checks[] = self::warn('description_too_long', ['length' => $descLen, 'max' => self::DESC_MAX]);
        } elseif ($descLen < self::DESC_MIN) {
            $checks[] = self::info('description_short', ['length' => $descLen, 'min' => self::DESC_MIN]);
        } else {
            $checks[] = self::ok('description_ok');
        }

        // صورة المشاركة (og/cover) — مطلوبة لبطاقات المشاركة + structured data.
        $hasShareImage = $article->og_image_id !== null
            || $article->mediaAssets->contains(fn (MediaAsset $a): bool => $a->pivot->collection === 'cover');
        $checks[] = $hasShareImage ? self::ok('cover_ok') : self::warn('cover_missing');

        // عنوان SEO مخصّص (يُفضَّل على الاشتقاق من العنوان).
        if ($article->seo_title === null || $article->seo_title === '') {
            $checks[] = self::info('seo_title_derived');
        }

        // canonical مخصّص يشير لمكان آخر — تنبيه (قد يمنع فهرسة هذه النسخة).
        if ($article->canonical_url !== null && $article->canonical_url !== ''
            && $article->canonical_url !== PublicSeoBuilder::absoluteUrl($article->canonicalPath())) {
            $checks[] = self::info('canonical_overridden', ['canonical' => $article->canonical_url]);
        }

        // robots noindex — تنبيه واضح (متعمَّد غالباً لكن يستحقّ الإبراز).
        if ($article->robots !== null && str_contains(mb_strtolower($article->robots), 'noindex')) {
            $checks[] = self::warn('robots_noindex');
        }

        return $checks;
    }

    /** @param array<string,mixed> $detail */
    private static function ok(string $key, array $detail = []): array
    {
        return ['key' => $key, 'severity' => 'ok', 'detail' => $detail];
    }

    /** @param array<string,mixed> $detail */
    private static function info(string $key, array $detail = []): array
    {
        return ['key' => $key, 'severity' => 'info', 'detail' => $detail];
    }

    /** @param array<string,mixed> $detail */
    private static function warn(string $key, array $detail = []): array
    {
        return ['key' => $key, 'severity' => 'warn', 'detail' => $detail];
    }
}
