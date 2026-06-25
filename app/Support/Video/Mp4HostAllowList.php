<?php

declare(strict_types=1);

namespace App\Support\Video;

use App\Support\Security\SafeUrl;

/**
 * تحقّق صارم من روابط MP4 المباشرة لمكتبة الفيديو — طبقتان دفاعيّتان:
 *   1) SafeUrl::isPublicHttps — https + مضيف عام (يمنع loopback/خاص/link-local/SSRF).
 *   2) allow-list مضيفات صريحة (config('video.mp4_allowed_hosts')) — مطابقة المضيف
 *      نفسه أو كنطاق فرعي (suffix). فارغة ⇒ رفض الكلّ (افتراضي آمن).
 *
 * لا تضمين iframe عشوائي ولا روابط MP4 من أي مضيف غير مُصرَّح.
 */
final class Mp4HostAllowList
{
    public static function permits(string $url): bool
    {
        $url = trim($url);
        if (! SafeUrl::isPublicHttps($url)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }

        $allowed = (array) config('video.mp4_allowed_hosts', []);
        if ($allowed === []) {
            return false; // allow-list فارغة ⇒ رفض صارم
        }

        foreach ($allowed as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate === '') {
                continue;
            }
            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }
}
