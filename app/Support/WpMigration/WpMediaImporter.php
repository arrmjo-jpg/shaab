<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Actions\Admin\Media\StoreMediaAssetAction;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Security\SafeUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * يستورد صور المتن/الصورة البارزة إلى مكتبة MediaAsset ويعيد كتابة عقد الصور.
 *
 * القواعد: ديدوب عالمي عبر StoreMediaAssetAction (SHA-256) #1؛ إعادة كتابة من
 * المصدر القانوني فقط #1؛ ديدوب لكل-منشور (memo) — استيراد مرّة، كتابة مراراً #2؛
 * حدّ تشعّب لكل منشور #3؛ حدّ حجم حتميّ #4؛ تحقّق MIME بالمحتوى (finfo) #5؛
 * جلب خارجي بأمان SSRF (SafeUrl) + مهلة/محاولات/تحديد إعادة توجيه #3/#4؛
 * عند الفشل: يُبقي المرجع الأصلي ويُسجّل تحذيراً مُصنَّفاً — لا يُفسد المتن #1.
 */
final class WpMediaImporter
{
    public function __construct(
        private readonly WpMediaResolver $resolver,
        private readonly User $actor,
    ) {}

    /**
     * يعيد كتابة عقد الصور في مستند TipTap (من المصدر القانوني).
     *
     * @param  array<string,mixed>  $doc
     */
    public function rewriteDoc(array $doc): MediaRewriteResult
    {
        /** @var array<string,array{ok:bool,url:?string,asset_id:?int}> $memo */
        $memo = [];
        $imported = 0;
        $reused = 0;
        $attempts = 0;
        $warnings = [];
        $perPostMax = (int) config('wp-migration.media.per_post_max', 40);

        $resolveSrc = function (string $src) use (&$memo, &$imported, &$reused, &$attempts, &$warnings, $perPostMax): ?string {
            if (array_key_exists($src, $memo)) {
                if ($memo[$src]['ok']) {
                    $reused++;

                    return $memo[$src]['url'];
                }

                return null; // فشل سابق — يُبقى الأصل
            }

            if ($attempts >= $perPostMax) {
                $warnings[] = ['src' => $src, 'reason' => 'media_capped'];
                $memo[$src] = ['ok' => false, 'url' => null, 'asset_id' => null];

                return null;
            }

            $attempts++;
            $result = $this->import($src);
            if ($result['asset'] instanceof MediaAsset) {
                $url = $result['asset']->url();
                $memo[$src] = ['ok' => $url !== null, 'url' => $url, 'asset_id' => $result['asset']->id];
                if ($url !== null) {
                    $imported++;

                    return $url;
                }
            }

            $memo[$src] = $memo[$src] ?? ['ok' => false, 'url' => null, 'asset_id' => null];
            $warnings[] = ['src' => $src, 'reason' => $result['reason'] ?? 'media_unresolved'];

            return null;
        };

        $rewritten = $this->walk($doc, $resolveSrc);

        $assetBySrc = [];
        foreach ($memo as $src => $m) {
            if ($m['ok'] && $m['asset_id'] !== null) {
                $assetBySrc[$src] = $m['asset_id'];
            }
        }

        return new MediaRewriteResult($rewritten, $imported, $reused, $warnings, $assetBySrc);
    }

    /**
     * يستورد صورة واحدة (محلية/خارجية) → MediaAsset أو فشل مُصنَّف.
     *
     * @return array{asset: ?MediaAsset, reason: ?string}
     */
    public function import(string $src): array
    {
        $resolution = $this->resolver->resolve($src);

        if ($resolution->isUnresolved()) {
            return ['asset' => null, 'reason' => $resolution->reason ?? 'media_unresolved'];
        }

        return $resolution->isLocal()
            ? $this->importLocal((string) $resolution->path)
            : $this->importExternal((string) $resolution->url);
    }

    /** @return array{asset: ?MediaAsset, reason: ?string} */
    private function importLocal(string $path): array
    {
        $size = @filesize($path);
        if ($size === false) {
            return ['asset' => null, 'reason' => 'media_unresolved'];
        }
        if ($size > $this->maxBytes()) {
            return ['asset' => null, 'reason' => 'media_too_large'];
        }

        $mime = self::sniffMime($path);
        if (! $this->allowedMime($mime)) {
            return ['asset' => null, 'reason' => 'media_unsupported_mime'];
        }

        try {
            $file = new UploadedFile($path, basename($path), $mime, null, true);

            return ['asset' => (new StoreMediaAssetAction)->handle($file, $this->actor), 'reason' => null];
        } catch (Throwable) {
            return ['asset' => null, 'reason' => 'persist_failed'];
        }
    }

    /** @return array{asset: ?MediaAsset, reason: ?string} */
    private function importExternal(string $url): array
    {
        if (! SafeUrl::isPublicHttps($url)) {
            return ['asset' => null, 'reason' => 'media_ssrf_blocked'];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wpmig');
        if ($tmp === false) {
            return ['asset' => null, 'reason' => 'media_network_error'];
        }

        try {
            $response = Http::timeout($this->timeout())
                ->retry($this->retries(), 200, throw: false)
                ->withOptions(['allow_redirects' => [
                    'max' => $this->maxRedirects(),
                    'strict' => true,
                    'protocols' => ['https'],
                ]])
                ->get($url);

            if (! $response->successful()) {
                return ['asset' => null, 'reason' => 'media_network_error'];
            }

            $body = (string) $response->body();
            if (strlen($body) > $this->maxBytes()) {
                return ['asset' => null, 'reason' => 'media_too_large'];
            }

            file_put_contents($tmp, $body);
            $mime = self::sniffMime($tmp);
            if (! $this->allowedMime($mime)) {
                return ['asset' => null, 'reason' => 'media_unsupported_mime'];
            }

            $file = new UploadedFile($tmp, 'remote-'.Str::random(8).self::extFor($mime), $mime, null, true);

            return ['asset' => (new StoreMediaAssetAction)->handle($file, $this->actor), 'reason' => null];
        } catch (Throwable) {
            return ['asset' => null, 'reason' => 'media_network_error'];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function walk(array $node, callable $resolveSrc): array
    {
        if (($node['type'] ?? '') === 'image' && isset($node['attrs']['src'])) {
            $url = $resolveSrc((string) $node['attrs']['src']);
            if ($url !== null) {
                $node['attrs']['src'] = $url; // نجاح → إعادة كتابة
            }
            // فشل → يُبقى المرجع الأصلي كما هو (لا إفساد للمتن)
        }

        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(
                fn ($child) => is_array($child) ? $this->walk($child, $resolveSrc) : $child,
                $node['content'],
            );
        }

        return $node;
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

    private function allowedMime(string $mime): bool
    {
        return in_array($mime, (array) config('wp-migration.media.allowed_mimes', []), true);
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

    private function maxBytes(): int
    {
        return (int) config('wp-migration.media.max_bytes', 26214400);
    }

    private function timeout(): int
    {
        return (int) config('wp-migration.media.fetch_timeout', 10);
    }

    private function retries(): int
    {
        return (int) config('wp-migration.media.fetch_retries', 2);
    }

    private function maxRedirects(): int
    {
        return (int) config('wp-migration.media.fetch_max_redirects', 2);
    }
}
