<?php

declare(strict_types=1);

namespace App\Support\Cache;

/**
 * باني مفاتيح الكاش — مصدر الحقيقة الوحيد لبنية المفاتيح.
 *
 * يُنتج مفاتيح منطقية بصيغة: resource:scope:identifier
 * البادئة العامة (alphacms) تُطبَّق تلقائياً من طبقة الكاش
 * عبر config/cache.php → CACHE_PREFIX. لا تكرار للبادئة هنا.
 *
 * مثال: CacheKeys::settings('general') => "settings:general"
 *       المخزَّن فعلياً في Redis => "alphacms:settings:general"
 *
 * لا منطق، لا I/O — بناء نصوص فقط. الاستخدام عبر Cache facade مباشرة.
 */
final class CacheKeys
{
    public static function make(string ...$segments): string
    {
        return implode(':', $segments);
    }

    // ─── Settings ──────────────────────────────────────────────────────
    public static function settings(string $group): string
    {
        return self::make('settings', $group);
    }

    // ─── Roles ─────────────────────────────────────────────────────────
    public static function rolesList(): string
    {
        return self::make('roles', 'list');
    }

    public static function role(int $id): string
    {
        return self::make('roles', 'id', (string) $id);
    }

    // ─── Permissions ───────────────────────────────────────────────────
    public static function permissionsGrouped(): string
    {
        return self::make('permissions', 'grouped');
    }

    public static function permissionGroups(): string
    {
        return self::make('permissions', 'groups');
    }

    // ─── Users ─────────────────────────────────────────────────────────
    public static function usersList(int $page = 1): string
    {
        return self::make('users', 'list', 'page', (string) $page);
    }

    // ─── Categories ────────────────────────────────────────────────────
    public static function categoriesTreeAdmin(): string
    {
        return self::make('categories', 'tree', 'admin');
    }

    public static function categoriesTreePublic(string $locale): string
    {
        return self::make('categories', 'tree', 'public', $locale);
    }

    // ─── Public content (P7) ───────────────────────────────────────────
    public static function publicArticlesList(string $locale, string $queryHash): string
    {
        return self::make('public', 'articles', $locale, $queryHash);
    }

    public static function publicArticleDetail(string $locale, string $slug): string
    {
        return self::make('public', 'article', $locale, $slug);
    }

    public static function publicArticlesMostRead(string $locale, int $limit, int $days): string
    {
        return self::make('public', 'articles', 'most-read', $locale, (string) $limit, (string) $days);
    }

    public static function publicArticlesTrending(string $locale, int $limit): string
    {
        return self::make('public', 'articles', 'trending', $locale, (string) $limit);
    }

    public static function publicCategoryDetail(string $locale, string $slug): string
    {
        return self::make('public', 'category', $locale, $slug);
    }

    // ─── Public reels ───────────────────────────────────────────────────
    public static function publicReelsList(string $locale, string $queryHash): string
    {
        return self::make('public', 'reels', $locale, $queryHash);
    }

    public static function publicReelDetail(string $locale, string $slug): string
    {
        return self::make('public', 'reel', $locale, $slug);
    }

    public static function publicReelsFeatured(string $locale, int $limit): string
    {
        return self::make('public', 'reels', 'featured', $locale, (string) $limit);
    }

    public static function publicReelsTrending(string $locale, int $limit): string
    {
        return self::make('public', 'reels', 'trending', $locale, (string) $limit);
    }

    // ─── Public video library ───────────────────────────────────────────
    public static function publicVideosList(string $locale, string $queryHash): string
    {
        return self::make('public', 'videos', $locale, $queryHash);
    }

    public static function publicVideoDetail(string $locale, string $slug): string
    {
        return self::make('public', 'video', $locale, $slug);
    }

    public static function publicVideosFeatured(string $locale, int $limit): string
    {
        return self::make('public', 'videos', 'featured', $locale, (string) $limit);
    }

    public static function publicVideosTrending(string $locale, int $limit): string
    {
        return self::make('public', 'videos', 'trending', $locale, (string) $limit);
    }

    public static function publicVideosByCategory(string $locale, string $categorySlug, string $queryHash): string
    {
        return self::make('public', 'videos', 'category', $locale, $categorySlug, $queryHash);
    }

    public static function publicRelatedVideos(string $locale, string $slug, int $limit): string
    {
        return self::make('public', 'videos', 'related', $locale, $slug, (string) $limit);
    }

    public static function publicPlaylistsList(string $locale, string $queryHash): string
    {
        return self::make('public', 'playlists', $locale, $queryHash);
    }

    public static function publicPlaylistDetail(string $locale, string $slug): string
    {
        return self::make('public', 'playlist', $locale, $slug);
    }

    // ─── Public static pages ────────────────────────────────────────────
    public static function publicPagesList(string $locale, string $queryHash): string
    {
        return self::make('public', 'pages', $locale, $queryHash);
    }

    public static function publicPageDetail(string $locale, string $slug): string
    {
        return self::make('public', 'page', $locale, $slug);
    }

    // ─── Public team members — نطاق مستقل عربي فقط (لا بادئة لغة) ─────────
    public static function publicTeamList(): string
    {
        return self::make('public', 'team', 'list');
    }

    public static function publicTeamDetail(string $slug): string
    {
        return self::make('public', 'team', $slug);
    }

    /** P7.2 — homepage feed for a single kind (zone or 'latest'). */
    public static function publicFeed(string $locale, string $kind, int $limit): string
    {
        return self::make('public', 'feed', $locale, $kind, (string) $limit);
    }

    /** P7.2 — full homepage aggregate (deterministic limits-per-zone). */
    public static function publicHomepage(string $locale): string
    {
        return self::make('public', 'homepage', $locale);
    }

    /** P8.3 — public live updates page (fingerprint busts on any timeline change). */
    public static function publicLiveUpdates(
        string $locale,
        string $slug,
        int $page,
        int $perPage,
        string $fingerprint
    ): string {
        return self::make('public', 'live', $locale, $slug, (string) $page, (string) $perPage, $fingerprint);
    }

    // ─── Public broadcasts (B4) — نطاق مستقل عربي فقط (لا بادئة لغة) ──────
    public static function publicBroadcastsList(string $kind, string $queryHash): string
    {
        return self::make('public', 'broadcasts', $kind, $queryHash);
    }

    public static function publicBroadcastDetail(string $kind, string $slug): string
    {
        return self::make('public', 'broadcast', $kind, $slug);
    }

    // ─── Advertising — بِركة الخدمة مُجزّأة بـ (zone, locale, device) ──────
    public static function adZonePool(string $zoneKey, string $locale, string $device): string
    {
        return self::make('ads', 'pool', $zoneKey, $locale, $device);
    }

    // ─── Polls (تحليلات — حساب-عند-القراءة، Phase 4) ──────────────────────
    /** تحليلات الأسطول (عبر-الاستطلاعات) — مفتاح ثابت مُصدَّر (v1). */
    public static function pollAnalytics(): string
    {
        return self::make('polls', 'analytics', 'fleet', 'v1');
    }

    /**
     * تحليلات استطلاع مفرد — مُجزّأة بالنطاق الزمني + بصمة طزاجة (updated_at)
     * فيُبطَل كاش الاستطلاع المغلق (TTL طويل) تلقائياً عند أي تعديل/إعادة تفعيل.
     */
    public static function pollEntityAnalytics(int $pollId, string $window, string $signature): string
    {
        return self::make('polls', 'analytics', 'entity', (string) $pollId, $window, $signature);
    }

    // ─── Site analytics dashboard (لوحة موحّدة — حساب عند القراءة) ─────────
    /** تحليلات الموقع الموحّدة — مفتاح ثابت مُصدَّر (v1). */
    public static function siteAnalytics(): string
    {
        return self::make('site', 'analytics', 'v1');
    }

    // ─── Account (لوحة المستخدم) — إحصاءات per-user ───────────────────────
    /** إحصاءات لوحة المستخدم — مفتاح لكل مستخدم. */
    public static function accountStats(int $userId): string
    {
        return self::make('account', 'stats', (string) $userId);
    }
}
