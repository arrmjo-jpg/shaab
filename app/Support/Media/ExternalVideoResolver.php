<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\VideoProvider;
use App\Support\Security\SafeUrl;

/**
 * محلّل روابط الفيديو الخارجي (allow-list). يكتشف المزوّد ويستخرج المعرّف
 * ويبني رابط تضمين آمناً قابلاً للعرض في <iframe>/<video>.
 *
 * مُكيَّف من منطق sawt القديم (VideoUrlParser + VideoEmbedUrlSanitizer):
 *   - حماية ضد انتحال النطاق (attacker.com.youtube.com مرفوض).
 *   - دعم YouTube (watch/youtu.be/shorts/live/embed)، Vimeo (+hash خاص)،
 *     TikTok، Instagram (p/reel/tv)، Facebook، X، وروابط MP4 المباشرة.
 *
 * @return array{provider:string,provider_id:?string,embed_url:string,source_url:string,poster_url:?string}|null
 */
final class ExternalVideoResolver
{
    public static function resolve(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || empty($parts['scheme'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || str_contains($host, '..') || str_starts_with($host, '.')) {
            return null;
        }
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return match (true) {
            self::hostIs($host, ['youtube.com', 'youtu.be', 'm.youtube.com', 'youtube-nocookie.com']) => self::youtube($url),
            self::hostIs($host, ['vimeo.com', 'player.vimeo.com']) => self::vimeo($url),
            self::hostIs($host, ['tiktok.com', 'vm.tiktok.com']) => self::tiktok($url),
            self::hostIs($host, ['instagram.com']) => self::instagram($url),
            self::hostIs($host, ['facebook.com', 'fb.watch']) => self::facebook($url),
            self::hostIs($host, ['x.com', 'twitter.com']) => self::twitter($url),
            default => self::directMp4($url, $parts),
        };
    }

    /** @param array<int,string> $bases */
    private static function hostIs(string $host, array $bases): bool
    {
        foreach ($bases as $base) {
            if ($host === $base || str_ends_with($host, '.'.$base)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed>|null */
    private static function youtube(string $url): ?array
    {
        $id = null;
        if (preg_match('#youtube\.com/watch\?(?:.*&)?v=([\w-]{6,20})#i', $url, $m)
            || preg_match('#youtu\.be/([\w-]{6,20})#i', $url, $m)
            || preg_match('#youtube\.com/shorts/([\w-]{6,20})#i', $url, $m)
            || preg_match('#youtube\.com/live/([\w-]{6,20})#i', $url, $m)
            || preg_match('#youtube(?:-nocookie)?\.com/embed/([\w-]{6,20})#i', $url, $m)) {
            $id = $m[1];
        }
        if ($id === null) {
            return null;
        }

        return self::shape(
            VideoProvider::YouTube,
            $id,
            "https://www.youtube.com/embed/{$id}?rel=0&modestbranding=1",
            $url,
            "https://img.youtube.com/vi/{$id}/hqdefault.jpg",
        );
    }

    /** @return array<string,mixed>|null */
    private static function vimeo(string $url): ?array
    {
        if (! preg_match('#vimeo\.com/(?:video/)?(\d{6,12})(?:/([a-f0-9]+))?#i', $url, $m)) {
            return null;
        }
        $id = $m[1];
        $hash = $m[2] ?? null;
        $embed = "https://player.vimeo.com/video/{$id}".($hash ? "?h={$hash}" : '');

        return self::shape(VideoProvider::Vimeo, $id, $embed, $url, null);
    }

    /** @return array<string,mixed>|null */
    private static function tiktok(string $url): ?array
    {
        if (! preg_match('#tiktok\.com/@[\w.-]+/video/(\d{6,25})#i', $url, $m)) {
            return null; // الروابط المختصرة (vm.tiktok.com) لا تحمل المعرّف
        }
        $id = $m[1];

        return self::shape(
            VideoProvider::TikTok,
            $id,
            "https://www.tiktok.com/embed/v2/{$id}",
            $url,
            null,
        );
    }

    /** @return array<string,mixed>|null */
    private static function instagram(string $url): ?array
    {
        if (! preg_match('#instagram\.com/(p|reel|tv)/([\w-]{5,20})#i', $url, $m)) {
            return null;
        }
        $type = strtolower($m[1]);
        $code = $m[2];

        return self::shape(
            VideoProvider::Instagram,
            $code,
            "https://www.instagram.com/{$type}/{$code}/embed",
            $url,
            null,
        );
    }

    /** @return array<string,mixed>|null */
    private static function facebook(string $url): ?array
    {
        // المعرّف اختياري (fb.watch لا يحمله) — التضمين عبر href
        $id = null;
        if (preg_match('#/videos/(\d{6,25})#', $url, $m) || preg_match('#[?&]v=(\d{6,25})#', $url, $m)) {
            $id = $m[1];
        }
        $embed = 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false';

        return self::shape(VideoProvider::Facebook, $id, $embed, $url, null);
    }

    /** @return array<string,mixed>|null */
    private static function twitter(string $url): ?array
    {
        if (! preg_match('#/status/(\d{6,25})#', $url, $m)) {
            return null;
        }
        $id = $m[1];
        // معاينة عبر twitframe (إطار آمن لمنشورات X)
        $embed = 'https://twitframe.com/show?url='.rawurlencode($url);

        return self::shape(VideoProvider::X, $id, $embed, $url, null);
    }

    /**
     * @param  array<string,mixed>  $parts
     * @return array<string,mixed>|null
     */
    private static function directMp4(string $url, array $parts): ?array
    {
        $path = strtolower((string) ($parts['path'] ?? ''));
        if (! preg_match('/\.(mp4|webm|mov|m4v)$/', $path)) {
            return null;
        }

        // أمان: لا تقبل إلا https على مضيف عام (يمنع http/المضيفات الداخلية).
        if (! SafeUrl::isPublicHttps($url)) {
            return null;
        }

        return self::shape(VideoProvider::Mp4, null, $url, $url, null);
    }

    /** @return array<string,mixed> */
    private static function shape(
        VideoProvider $provider,
        ?string $providerId,
        string $embedUrl,
        string $sourceUrl,
        ?string $posterUrl,
    ): array {
        return [
            'provider' => $provider->value,
            'provider_id' => $providerId,
            'embed_url' => $embedUrl,
            'source_url' => $sourceUrl,
            'poster_url' => $posterUrl,
        ];
    }
}
