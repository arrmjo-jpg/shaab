<?php

declare(strict_types=1);

namespace App\Support\Vertix;

/**
 * توليد رابط صورة Vertix من الأجزاء الخام — لا يُخزَّن الرابط الكامل إطلاقاً.
 * النمط: {cdn_base}/{folder}/{image_segment}/{ph_name}
 * مثال: https://cdn.alqalahnews.net/2019-08-17/images/224475_1_1566024477.jpeg
 */
final class VertixImageUrl
{
    public static function build(?string $folder, ?string $phName): ?string
    {
        $folder = trim((string) $folder);
        $phName = trim((string) $phName);
        if ($folder === '' || $phName === '') {
            return null;
        }

        $base = rtrim((string) config('vertix.cdn_base', 'https://cdn.alqalahnews.net'), '/');
        $segment = trim((string) config('vertix.image_segment', 'images'), '/');

        return $base.'/'.trim($folder, '/').'/'.$segment.'/'.ltrim($phName, '/');
    }
}
