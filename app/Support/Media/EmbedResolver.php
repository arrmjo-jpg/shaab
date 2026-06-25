<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\EmbedProvider;

/**
 * محلّل تضمينات allow-list فقط. يرفض أي مزوّد خارج القائمة.
 * يُرجع ['provider','id','embed_url'] أو null عند عدم الدعم.
 */
final class EmbedResolver
{
    public static function resolve(string $url): ?array
    {
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        return match (true) {
            $host === 'youtu.be' || $host === 'youtube.com' || $host === 'm.youtube.com' => self::youtube($url),
            $host === 'vimeo.com' || $host === 'player.vimeo.com' => self::vimeo($url),
            $host === 'twitter.com' || $host === 'x.com' => self::statusbased($url, EmbedProvider::Twitter),
            $host === 'facebook.com' || $host === 'fb.watch' => self::passthrough($url, EmbedProvider::Facebook),
            $host === 'instagram.com' => self::passthrough($url, EmbedProvider::Instagram),
            default => null,
        };
    }

    private static function youtube(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (str_contains($host, 'youtu.be')) {
            $id = trim((string) parse_url($url, PHP_URL_PATH), '/');
        } else {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $id = $q['v'] ?? null;
        }
        if (! $id || ! preg_match('/^[\w-]{6,20}$/', $id)) {
            return null;
        }

        return ['provider' => EmbedProvider::YouTube->value, 'id' => $id,
            'embed_url' => "https://www.youtube.com/embed/{$id}"];
    }

    private static function vimeo(string $url): ?array
    {
        if (! preg_match('#vimeo\.com/(?:video/)?(\d{6,12})#', $url, $m)) {
            return null;
        }

        return ['provider' => EmbedProvider::Vimeo->value, 'id' => $m[1],
            'embed_url' => "https://player.vimeo.com/video/{$m[1]}"];
    }

    private static function statusbased(string $url, EmbedProvider $p): ?array
    {
        if (! preg_match('#/status/(\d{6,25})#', $url, $m)) {
            return null;
        }

        return ['provider' => $p->value, 'id' => $m[1], 'embed_url' => $url];
    }

    private static function passthrough(string $url, EmbedProvider $p): array
    {
        return ['provider' => $p->value, 'id' => null, 'embed_url' => $url];
    }
}
