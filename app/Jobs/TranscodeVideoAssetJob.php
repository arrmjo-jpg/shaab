<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\MediaProcessingProfile;
use App\Models\MediaAsset;
use App\Support\Media\RemoteStorage;
use App\Support\Media\VideoTranscoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * تحويل فيديو مرفوع إلى HLS متعدّد الدقّات + poster (queue=media — Wave 3).
 *
 * المصدر يبقى نظيفاً. المخرجات تُكتب بجانب الأصل على نفس القرص:
 *   assets/{uuid}/hls/master.m3u8 + stream_x/...
 *   assets/{uuid}/poster.jpg
 *
 * دورة الحالة: queued → processing → ready | failed (عمود processing_status).
 * يكتب عبر Eloquent (يحترم Rule 2)؛ الحقول غير مُدقَّقة فلا ضجيج تدقيق.
 */
class TranscodeVideoAssetJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 2400;

    // أكبر من timeout: قفل التفرّد يبقى قائماً طوال الترميز فلا يُجدوَل نظير مكرّر
    // أثناء التنفيذ. (يقترن بـ retry_after مرتفع على اتصال redis-media.)
    public int $uniqueFor = 2700;

    // انتهاء المهلة على مهمة ترميز ثقيلة = ملف مشكِل؛ نفشل فوراً بلا إعادة محاولة
    // مكلفة (40 دقيقة إضافية)، ويُسجَّل الأصل failed لاسترجاع يدوي.
    public bool $failOnTimeout = true;

    /**
     * مخرجات المعالجة غير قابلة للتغيير: كل ملف تحت assets/{uuid}/ بمسار ثابت،
     * واستبدال الفيديو يُنشئ uuid جديداً (رابطاً جديداً) — فلا تقادم. لذا نضبط
     * Cache-Control طويل/immutable لتفريغ الأصل من ضربات الحافة (يسري على
     * S3/R2؛ يُتجاهَل بأمان على القرص المحلّي حيث يضبطه خادم الويب).
     */
    private const IMMUTABLE_CACHE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly int $mediaAssetId)
    {
        // توجيه صريح عبر الخصائص (الاصطلاح المعتمد في ProcessCdnPurgeBatch):
        // طابور media + اتصال الوسائط الطويل (null في الاختبارات ⇒ افتراضي/sync).
        $this->onQueue('media');
        $this->onConnection(config('queue.media_connection'));
    }

    public function uniqueId(): string
    {
        return 'transcode-video-'.$this->mediaAssetId;
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [120, 300];
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset === null || ! $asset->isUploadedVideo()) {
            return;
        }

        $disk = Storage::disk($asset->disk);
        if (! $disk->exists($asset->path)) {
            $this->fail($asset, 'source_missing');

            return;
        }

        $asset->forceFill(['processing_status' => 'processing'])->save();

        $workDir = $this->makeTempDir();
        $tmpSource = $workDir.DIRECTORY_SEPARATOR.'source';

        try {
            // أمان الذاكرة: انسخ المصدر بثّاً إلى ملف مؤقّت بدل تحميله كاملاً في
            // الذاكرة ($disk->get) — يدعم الفيديو الكبير دون استنزاف ذاكرة العامل.
            $this->streamSourceToDisk($disk, $asset->path, $tmpSource);

            $probe = $transcoder->probe($tmpSource);

            // ثبّت البيانات الوصفية مبكراً (تتبّع حبيبي: «استُخرجت البيانات الوصفية»).
            $asset->forceFill([
                'duration_seconds' => $probe['duration'],
                'width' => $asset->width ?? $probe['width'],
                'height' => $asset->height ?? $probe['height'],
            ])->save();

            // تصلّب الرفع (Phase 4): حدود ما بعد الـ probe — مدّة/أبعاد/قابلية فك
            // الترميز. الرفض هنا يمنع ترميزاً مكلفاً لمحتوى مخالف/تالف.
            if ($reason = $this->probeRejection($asset, $probe)) {
                $this->fail($asset, $reason);

                return;
            }

            $conversions = (array) ($asset->conversions ?? []);
            $prefix = $this->dirPrefix($asset);

            // ── poster ──
            $posterLocal = $workDir.DIRECTORY_SEPARATOR.'poster.jpg';
            if ($transcoder->poster($tmpSource, $posterLocal)) {
                $posterPath = $prefix.'poster.jpg';
                $disk->put($posterPath, (string) file_get_contents($posterLocal), self::putOptions());
                [$pw, $ph] = @getimagesize($posterLocal) ?: [null, null];
                $conversions['poster'] = ['path' => $posterPath, 'width' => $pw, 'height' => $ph];
                $this->persistConversions($asset, $conversions); // تقدّم حبيبي
            }

            // ── HLS ──
            $hlsLocal = $workDir.DIRECTORY_SEPARATOR.'hls';
            $result = $transcoder->hls($tmpSource, $hlsLocal, (int) ($probe['height'] ?? 0));
            if (! $result['success']) {
                $this->fail($asset, 'hls_failed');

                return;
            }

            $this->uploadTree($disk, $hlsLocal, $prefix.'hls');
            $conversions['hls'] = [
                'master' => $prefix.'hls/master.m3u8',
                'variants' => $result['variants'],
            ];
            $this->persistConversions($asset, $conversions); // تقدّم حبيبي: HLS جاهز

            // ── ملف تعريف reel: نسخ MP4 تدريجية + صورة مصغّرة WebP (مجمّعة) ──
            // محايد للمحتوى: يُشغَّل فقط عند طلب الملف الصراحةً (لا تكلفة على غيره).
            if ($asset->processing_profile === MediaProcessingProfile::Reel->value) {
                $this->buildReelOutputs($asset, $transcoder, $disk, $tmpSource, $posterLocal, $workDir, $prefix, $probe, $conversions);
            }

            // نجاح: نظّف أي سبب فشل سابق (مثلاً عند إعادة معالجة أصل مرفوض سابقاً).
            $meta = (array) ($asset->metadata ?? []);
            unset($meta['processing_error']);

            $asset->forceFill([
                'conversions' => $conversions,
                'metadata' => $meta,
                'processing_status' => 'ready',
            ])->save();

            // التخزين الهجين: انسخ كل المشتقّات إلى المرآة البعيدة (إن فُعّلت).
            if (RemoteStorage::enabled()) {
                MirrorMediaToRemoteJob::dispatch($asset->id);
            }
        } catch (Throwable $e) {
            $this->fail($asset, 'transcode_error');
        } finally {
            $this->cleanup($workDir);
        }
    }

    /**
     * حدود ما بعد الـ probe (تصلّب الرفع): تُعيد سبب الرفض أو null إن مرّ.
     * undecodable: لا تيار فيديو قابل لفك الترميز (تالف/صوت فقط/ترميز غير مدعوم).
     *
     * @param  array<string,mixed>  $probe
     */
    private function probeRejection(MediaAsset $asset, array $probe): ?string
    {
        if ($probe['width'] === null || $probe['height'] === null) {
            return 'undecodable';
        }

        $maxDim = (int) config('performance.media.video_max_dimension', 8192);
        if ($probe['width'] > $maxDim || $probe['height'] > $maxDim) {
            return 'dimensions_exceeded';
        }

        $maxDuration = $asset->processing_profile === MediaProcessingProfile::Reel->value
            ? (int) config('performance.media.reel_max_duration', 180)
            : (int) config('performance.media.video_max_duration', 600);

        if ($probe['duration'] !== null && $probe['duration'] > $maxDuration) {
            return 'duration_exceeded';
        }

        return null;
    }

    /**
     * مخرجات ملف reel: thumbnail.webp (من poster) + نسخ MP4 (master + ladder)
     * مجمّعة بجوار الأصل تحت assets/{uuid}/. يُعدِّل $conversions بالمرجع.
     *
     * @param  array<string,mixed>  $probe
     * @param  array<string,mixed>  $conversions
     */
    private function buildReelOutputs(
        MediaAsset $asset,
        VideoTranscoder $transcoder,
        Filesystem $disk,
        string $tmpSource,
        string $posterLocal,
        string $workDir,
        string $prefix,
        array $probe,
        array &$conversions,
    ): void {
        // (A) صورة مصغّرة WebP — أفضل جهد؛ وإلا يبقى poster.jpg بديلاً آمناً.
        if (isset($conversions['poster']['path']) && is_file($posterLocal)) {
            $webpLocal = $workDir.DIRECTORY_SEPARATOR.'thumbnail.webp';
            $webpPath = null;
            if ($transcoder->thumbnailWebp($posterLocal, $webpLocal)) {
                $webpPath = $prefix.'thumbnail.webp';
                $disk->put($webpPath, (string) file_get_contents($webpLocal), self::putOptions());
            }
            $conversions['thumbnail'] = [
                'jpg' => $conversions['poster']['path'],
                'webp' => $webpPath,
            ];
            $this->persistConversions($asset, $conversions); // تقدّم حبيبي: مصغّرة
        }

        // (B) نسخ MP4 تدريجية مجمّعة flat تحت assets/{uuid}/ (لا مجلدات لكل دقّة).
        $renditionsLocal = $workDir.DIRECTORY_SEPARATOR.'renditions';
        $rend = $transcoder->renditions($tmpSource, $renditionsLocal, (int) ($probe['height'] ?? 0));
        if ($rend['success']) {
            $this->uploadTree($disk, $renditionsLocal, rtrim($prefix, '/'));
            $conversions['renditions'] = [
                'master' => $prefix.$rend['master'],
                'variants' => array_map(fn (string $name): string => $prefix.$name, $rend['variants']),
            ];
            $this->persistConversions($asset, $conversions); // تقدّم حبيبي: نسخ MP4
        }
    }

    /**
     * يحفظ تقدّم المشتقّات تدريجياً أثناء المعالجة (الحالة تبقى processing) كي
     * يرى المشغّل تقدّماً حبيبياً ومرحلة الفشل عند التعثّر. لا يلمس processing_status.
     *
     * @param  array<string,mixed>  $conversions
     */
    private function persistConversions(MediaAsset $asset, array $conversions): void
    {
        $asset->forceFill(['conversions' => $conversions])->save();
    }

    public function failed(?Throwable $e): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset !== null) {
            $this->fail($asset);
        }
    }

    /**
     * يُسجِّل الأصل failed مع سبب اختياري قابل للتشخيص (يُخزَّن في metadata
     * ويُسجَّل في اللوغ) — يغذّي عرض الفشل في لوحة الإدارة (Phase 2/5).
     */
    private function fail(MediaAsset $asset, ?string $reason = null): void
    {
        if ($reason !== null) {
            Log::warning('TranscodeVideoAssetJob: failed', [
                'asset_id' => $asset->id,
                'reason' => $reason,
                'duration' => $asset->duration_seconds,
                'width' => $asset->width,
                'height' => $asset->height,
            ]);

            $meta = (array) ($asset->metadata ?? []);
            $meta['processing_error'] = $reason;
            $asset->forceFill(['metadata' => $meta])->save();
        }

        $asset->forceFill(['processing_status' => 'failed'])->save();
    }

    private function dirPrefix(MediaAsset $asset): string
    {
        $dir = trim(dirname($asset->path), '/.');

        return $dir !== '' ? $dir.'/' : '';
    }

    /** يرفع شجرة ملفات محلّية إلى القرص تحت بادئة معطاة. */
    private function uploadTree(Filesystem $disk, string $localDir, string $remotePrefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($localDir))), '/');
            $disk->put($remotePrefix.'/'.$relative, (string) file_get_contents($file->getPathname()), self::putOptions());
        }
    }

    /**
     * خيارات الكتابة للمخرجات: عام + Cache-Control طويل/immutable.
     * S3/R2 يقرأ CacheControl؛ القرص المحلّي يتجاهله بأمان.
     *
     * @return array<string,string>
     */
    private static function putOptions(): array
    {
        return [
            'visibility' => 'public',
            'CacheControl' => self::IMMUTABLE_CACHE,
        ];
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'tv_'.uniqid('', true);
        @mkdir($dir, 0o755, true);

        return $dir;
    }

    /**
     * ينسخ ملف المصدر من القرص إلى ملف محلّي مؤقّت **بثّاً** (chunked) — لا تحميل
     * كامل في ذاكرة العامل. آمن للملفات الكبيرة. يرمي عند تعذّر الفتح/الكتابة كي
     * تلتقطه دورة الفشل القائمة (retries محفوظة).
     */
    private function streamSourceToDisk(Filesystem $disk, string $path, string $tmpSource): void
    {
        $src = $disk->readStream($path);
        if ($src === null || $src === false) {
            throw new \RuntimeException("Unable to open source stream for [{$path}].");
        }

        $dst = fopen($tmpSource, 'wb');
        if ($dst === false) {
            if (is_resource($src)) {
                fclose($src);
            }
            throw new \RuntimeException("Unable to open local temp file [{$tmpSource}].");
        }

        try {
            stream_copy_to_stream($src, $dst);
        } finally {
            if (is_resource($src)) {
                fclose($src);
            }
            fclose($dst);
        }
    }

    private function cleanup(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
