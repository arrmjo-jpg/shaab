<?php

declare(strict_types=1);

namespace App\Support\Vertix;

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Security\SafeUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * يُنزّل صورة Vertix البعيدة (CDN عامّ) ويُدخلها مكتبة MediaAsset المركزيّة.
 *
 * **مستقلّ تماماً عن WpMediaImporter** (التزاماً بقيد استقلال Vertix عن ترحيل ووردبريس):
 * يعيد استخدام القطع المحايدة فقط — SafeUrl (حماية SSRF) + Http + StoreMediaAssetAction
 * (التخزين على القرص + ديدوب SHA-256 العالميّ + جدولة توليد المصغّرات WebP).
 *
 * فشل التنزيل/التحقّق ⇒ null (لا يُسقط استيراد الخبر؛ يُستورَد بلا غلاف). الديدوب يضمن
 * أنّ صورةً مكرّرة المحتوى لا تُخزَّن مرّتين (يُعاد الأصل الموجود) — Idempotent.
 */
final class VertixImageImporter
{
    /** ينزّل رابطاً عامّاً معروفاً → MediaAsset، أو null عند أيّ فشل (آمن، بلا رمي). */
    public static function fetch(string $url, User $actor): ?MediaAsset
    {
        if (! SafeUrl::isPublicHttps($url)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'vertix');
        if ($tmp === false) {
            return null;
        }

        try {
            $response = Http::timeout(self::cfg('fetch_timeout', 10))
                ->retry(self::cfg('fetch_retries', 2), 200, throw: false)
                ->withOptions(['allow_redirects' => [
                    'max' => self::cfg('fetch_max_redirects', 2),
                    'strict' => true,
                    'protocols' => ['https'],
                ]])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = (string) $response->body();
            if ($body === '' || strlen($body) > self::cfg('max_bytes', 26214400)) {
                return null;
            }

            file_put_contents($tmp, $body);
            $mime = self::sniffMime($tmp);
            if (! in_array($mime, self::allowedMimes(), true)) {
                return null;
            }

            $file = new UploadedFile($tmp, 'vertix-'.Str::random(8).self::extFor($mime), $mime, null, true);

            return (new StoreMediaAssetAction)->handle($file, $actor);
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    private static function sniffMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }
        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime;
    }

    /** @return array<int,string> */
    private static function allowedMimes(): array
    {
        return (array) config('vertix.media.allowed_mimes', ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif']);
    }

    private static function extFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/webp' => '.webp',
            'image/gif' => '.gif',
            'image/avif' => '.avif',
            default => '',
        };
    }

    private static function cfg(string $key, int $default): int
    {
        return (int) config('vertix.media.'.$key, $default);
    }
}
