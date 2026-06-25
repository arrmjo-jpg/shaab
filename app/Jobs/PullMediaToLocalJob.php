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
 * يوطّن أصلاً بعيداً فقط (legacy remote-only) بسحب شجرته من البعيد إلى القرص
 * المحلّي canonical — لاستمرارية الأعمال وإزالة الاعتماد على الأصول البعيدة فقط.
 *
 * - آمن الذاكرة: بثّ (readStream/writeStream) لا تحميل كامل.
 * - idempotent: يتخطّى الملفات الموجودة محلّياً؛ إعادة المحاولة آمنة.
 * - لا كسر روابط: التبديل إلى المحلّي يتمّ ذرّياً بعد اكتمال السحب (حتى ذلك
 *   الحين يُخدَم الأصل من البعيد عبر المُحلِّل).
 */
class PullMediaToLocalJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<int,string> */
    private const LOCAL_DISKS = ['uploads', 'public', 'local'];

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(private readonly int $mediaAssetId)
    {
        $this->onQueue('media');
        $this->onConnection(config('queue.media_connection'));
    }

    public function uniqueId(): string
    {
        return 'pull-media-'.$this->mediaAssetId;
    }

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset === null || $asset->isExternal() || $asset->stored_local) {
            return; // غير موجود/خارجي/موطَّن سلفاً — لا شيء
        }

        // أعِد بناء قرص المرآة من الإعدادات الحالية (worker طويل العمر).
        RemoteStorage::configureDisk();

        $remoteName = in_array($asset->disk, self::LOCAL_DISKS, true)
            ? RemoteStorage::diskName()
            : $asset->disk; // legacy remote-only ⇒ قرصه (s3)

        try {
            $remote = Storage::disk($remoteName);
        } catch (Throwable $e) {
            $this->markFailed($asset, 'remote_disk_unconfigured');

            return;
        }

        $localName = (string) config('media-library.canonical_disk', 'uploads');
        $local = Storage::disk($localName);

        $prefix = trim(dirname($asset->path), '/.');
        $files = $prefix !== '' ? $remote->allFiles($prefix) : [$asset->path];

        if ($files === []) {
            $this->markFailed($asset, 'remote_objects_missing');

            return;
        }

        try {
            foreach ($files as $key) {
                if ($local->exists($key)) {
                    continue; // idempotent
                }
                $stream = $remote->readStream($key);
                if ($stream === null) {
                    continue;
                }
                $local->writeStream($key, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // تبديل ذرّي إلى المحلّي canonical (يبقى البعيد كمرآة).
            $asset->forceFill([
                'disk' => $localName,
                'stored_local' => true,
                'stored_remote' => true,
                'remote_path' => $asset->path,
                'preferred_delivery' => 'auto',
                'remote_sync_status' => 'synced',
                'remote_sync_error' => null,
                'last_remote_sync_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->markFailed($asset, $e->getMessage());
        }
    }

    public function failed(?Throwable $e): void
    {
        $asset = MediaAsset::query()->find($this->mediaAssetId);
        if ($asset !== null) {
            $this->markFailed($asset, $e?->getMessage());
        }
    }

    private function markFailed(MediaAsset $asset, ?string $reason): void
    {
        Log::warning('PullMediaToLocalJob: failed', ['asset_id' => $asset->id, 'reason' => $reason]);
        $asset->forceFill([
            'remote_sync_error' => $reason !== null ? mb_substr($reason, 0, 500) : null,
        ])->save();
    }
}
