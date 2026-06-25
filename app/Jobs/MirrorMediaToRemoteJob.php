<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Support\Media\RemoteStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ينسخ كل ملفات الأصل من القرص المحلّي (canonical) إلى مرآة R2 (queue=media).
 *
 * - idempotent: يتخطّى الملفات الموجودة على البعيد (المحتوى ثابت — نفس المفتاح =
 *   نفس البايتات)، فإعادة المحاولة آمنة بلا رفع مزدوج.
 * - يَنسخ كل المشتقّات: الأصل + poster + مصغّرات/WebP + نسخ MP4 + master +
 *   manifests الـ HLS + مقاطعها (يرفع شجرة assets/{uuid}/ كاملة).
 * - Cache-Control طويل/immutable على كل كائن مرآة.
 * - عزل الفشل: المحلّي canonical يبقى سليماً؛ الفشل يُسجَّل remote_sync_status=failed
 *   ولا يكسر شيئاً. لا يُمرَّر visibility (R2 لا يدعم ACL).
 */
class MirrorMediaToRemoteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    private const IMMUTABLE_CACHE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly int $mediaAssetId)
    {
        $this->onQueue('media');
        $this->onConnection(config('queue.media_connection'));
    }

    public function uniqueId(): string
    {
        return 'mirror-media-'.$this->mediaAssetId;
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        if (! RemoteStorage::enabled()) {
            return; // المرآة معطّلة — لا شيء يُفعل
        }

        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset === null || $asset->isExternal()) {
            return; // خارجي = لا ملفات
        }

        // أعِد بناء قرص المرآة من الإعدادات الحالية — يلتقط الـ worker الطويل
        // العمر أي تفعيل/تغيير من اللوحة بلا إعادة تشغيل يدوي.
        RemoteStorage::configureDisk();

        // قرص المرآة قد يكون غير مُهيّأ/اعتماديات خاطئة — fail-safe، لا كسر.
        try {
            $remote = Storage::disk(RemoteStorage::diskName());
        } catch (Throwable $e) {
            $this->failSync($asset, 'remote_disk_unconfigured');

            return;
        }

        $local = Storage::disk($asset->disk);
        $prefix = trim(dirname($asset->path), '/.');
        $files = $prefix !== '' ? $local->allFiles($prefix) : [$asset->path];

        $asset->forceFill(['remote_sync_status' => 'syncing'])->save();

        try {
            foreach ($files as $key) {
                // idempotent: لا ترفع ما هو موجود (محتوى ثابت).
                if ($remote->exists($key)) {
                    continue;
                }
                // بثّ (stream) لا تحميل كامل في الذاكرة — آمن للملفات الكبيرة.
                $stream = $local->readStream($key);
                if ($stream === null) {
                    continue;
                }
                $remote->writeStream($key, $stream, ['CacheControl' => self::IMMUTABLE_CACHE]);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $asset->forceFill([
                'stored_remote' => true,
                'remote_path' => $asset->path,
                'remote_sync_status' => 'synced',
                'remote_sync_error' => null,
                'last_remote_sync_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->failSync($asset, $e->getMessage());
        }
    }

    public function failed(?Throwable $e): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset !== null) {
            $this->failSync($asset, $e?->getMessage());
        }
    }

    private function failSync(MediaAsset $asset, ?string $reason): void
    {
        Log::warning('MirrorMediaToRemoteJob: failed', [
            'asset_id' => $asset->id,
            'reason' => $reason,
        ]);

        $asset->forceFill([
            'remote_sync_status' => 'failed',
            'remote_sync_error' => $reason !== null ? mb_substr($reason, 0, 500) : null,
        ])->save();
    }
}
