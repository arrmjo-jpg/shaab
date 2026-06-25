<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Article;

/**
 * Constructs the public-facing SEO payload for an Article.
 *
 * Output is consumed by the SSR/SSG public site (separate codebase) which
 * renders the actual `<head>` tags. Backend remains the source of truth for:
 *   - Canonical URL
 *   - hreflang siblings (by translation_group)
 *   - OpenGraph + Twitter card data
 *   - JSON-LD NewsArticle / Article structured data
 *
 * Note on package reuse: ralphjsmit/laravel-seo's SEOData DTO is available for
 * Blade-rendered HTML pages; this builder mirrors that data structure as a
 * plain array for API delivery. Single source of truth either way.
 */
final class PublicSeoBuilder
{
    public static function build(Article $article): array
    {
        $absoluteUrl = self::absoluteUrl($article->canonicalPath());
        // صورة المشاركة المخصّصة (og:image) لها الأولوية، ثم الغلاف — مع أبعادها.
        $shareImage = self::shareImageObject($article);
        $coverUrl = $shareImage['url'] ?? null;
        $title = $article->seo_title !== null && $article->seo_title !== ''
            ? $article->seo_title
            : $article->title;
        $description = $article->seo_description !== null && $article->seo_description !== ''
            ? $article->seo_description
            : $article->excerpt;

        $siteName = (string) config('app.name', 'AlphaCMS');
        $twitterHandle = (string) config('seo.twitter.@username', '') ?: null;

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $article->seo_keywords,
            'canonical_url' => $article->canonical_url !== null && $article->canonical_url !== ''
                ? $article->canonical_url
                : $absoluteUrl,
            'robots' => $article->robots !== null && $article->robots !== ''
                ? $article->robots
                : (string) config('seo.robots.default'),
            'image' => $coverUrl,
            'hreflang' => self::hreflang($article),
            'og' => [
                'type' => 'article',
                'site_name' => $siteName,
                'locale' => self::ogLocale($article->locale),
                'title' => $title,
                'description' => $description,
                'url' => $absoluteUrl,
                'image' => $coverUrl,
                'image_width' => $shareImage['width'] ?? null,
                'image_height' => $shareImage['height'] ?? null,
                'article' => [
                    'published_time' => $article->published_at?->toISOString(),
                    'modified_time' => $article->updated_at?->toISOString(),
                    'section' => $article->primaryCategory?->name,
                    'tag' => $article->tags->pluck('name')->values()->all(),
                    'author' => $article->author?->name,
                ],
            ],
            'twitter' => [
                'card' => $coverUrl ? 'summary_large_image' : 'summary',
                'site' => $twitterHandle ? '@'.$twitterHandle : null,
                'creator' => $twitterHandle ? '@'.$twitterHandle : null,
                'title' => $title,
                'description' => $description,
                'image' => $coverUrl,
            ],
            'structured_data' => self::structuredData($article, $absoluteUrl, $shareImage, $title, $description, $siteName),
            // BreadcrumbList JSON-LD منفصل (المستهلك يُصدِره كـ script ثانٍ).
            'breadcrumbs' => self::breadcrumbs($article),
        ];
    }

    /** صورة المشاركة ككائن (url + أبعاد) — og_image ثم الغلاف، وإلا avatar للرأي. */
    private static function shareImageObject(Article $article): ?array
    {
        $asset = null;
        if ($article->og_image_id !== null) {
            $asset = $article->ogImage;
        }
        if ($asset === null) {
            $asset = $article->mediaAssets
                ->first(fn ($a): bool => $a->pivot->collection === 'cover');
        }

        if ($asset !== null) {
            return array_filter([
                'url' => $asset->url(),
                'width' => $asset->width,
                'height' => $asset->height,
            ], fn ($v): bool => $v !== null);
        }

        // بديل الرأي: صورة الكاتب (بلا أبعاد معروفة).
        if ($article->type->value === 'opinion') {
            $avatar = $article->authorAvatarUrl();
            if ($avatar !== null) {
                return ['url' => $avatar];
            }
        }

        return null;
    }

    /** BreadcrumbList: الرئيسية → التصنيف (إن وُجد) → المقال. */
    private static function breadcrumbs(Article $article): array
    {
        $locale = $article->locale;
        $items = [[
            '@type' => 'ListItem',
            'position' => 1,
            'name' => (string) (config('seo.publisher.name') ?: config('app.name', 'AlphaCMS')),
            'item' => self::absoluteUrl($locale),
        ]];

        $position = 2;
        if ($article->primaryCategory !== null) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $article->primaryCategory->name,
                'item' => self::absoluteUrl($locale.'/'.$article->primaryCategory->slug),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $article->title,
            'item' => self::absoluteUrl($article->canonicalPath()),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** @return array<int,array{locale:string,url:string}> */
    private static function hreflang(Article $article): array
    {
        $selfUrl = self::absoluteUrl($article->canonicalPath());

        if ($article->translation_group === null || $article->translation_group === '') {
            return [
                ['locale' => $article->locale, 'url' => $selfUrl],
                // x-default يشير للنسخة المتاحة (هذه) — توجيه افتراضي للزواحف.
                ['locale' => 'x-default', 'url' => $selfUrl],
            ];
        }

        $siblings = Article::query()
            ->where('translation_group', $article->translation_group)
            ->published()
            ->with('primaryCategory:id,slug')
            ->get();

        $list = $siblings
            ->map(fn (Article $a): array => [
                'locale' => $a->locale,
                'url' => self::absoluteUrl($a->canonicalPath()),
            ])
            ->values()
            ->all();

        // x-default → نسخة اللغة الأساسية (ar) إن وُجدت، وإلا هذه النسخة.
        $default = $siblings->firstWhere('locale', 'ar') ?? $article;
        $list[] = ['locale' => 'x-default', 'url' => self::absoluteUrl($default->canonicalPath())];

        return $list;
    }

    /** Build the JSON-LD NewsArticle schema. Consumer wraps in <script type="application/ld+json">. */
    private static function structuredData(
        Article $article,
        string $url,
        ?array $shareImage,
        string $title,
        ?string $description,
        string $siteName,
    ): array {
        $schemaType = $article->type->value === 'news' ? 'NewsArticle' : 'Article';

        // صورة كـ ImageObject مع الأبعاد إن توفّرت (أصلح لنتائج Google الغنية).
        $image = null;
        if ($shareImage !== null) {
            $image = isset($shareImage['width'], $shareImage['height'])
                ? array_filter([
                    '@type' => 'ImageObject',
                    'url' => $shareImage['url'],
                    'width' => $shareImage['width'],
                    'height' => $shareImage['height'],
                ], fn ($v): bool => $v !== null)
                : [$shareImage['url']];
        }

        // الناشر مع شعار ImageObject — مطلوب لصلاحية NewsArticle في Google.
        $logo = (string) config('seo.publisher.logo', '');
        $publisher = array_filter([
            '@type' => 'Organization',
            'name' => (string) (config('seo.publisher.name') ?: $siteName),
            'logo' => $logo !== '' ? ['@type' => 'ImageObject', 'url' => $logo] : null,
        ], fn ($v): bool => $v !== null);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'headline' => $title,
            'description' => $description,
            'image' => $image,
            'datePublished' => $article->published_at?->toISOString(),
            'dateModified' => $article->updated_at?->toISOString(),
            'author' => $article->author?->name
                ? ['@type' => 'Person', 'name' => $article->author->name]
                : null,
            'publisher' => $publisher,
            'articleSection' => $article->primaryCategory?->name,
            'keywords' => $article->seo_keywords,
            'inLanguage' => $article->locale,
        ], fn ($v) => $v !== null);
    }

    private static function ogLocale(string $locale): string
    {
        return match ($locale) {
            'ar' => 'ar_AR',
            'en' => 'en_US',
            default => $locale,
        };
    }

    public static function absoluteUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
