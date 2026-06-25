<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Support\Media\RemoteStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * يتحقّق من سلامة المرآة البعيدة للأصول المُعلَّمة synced: كائنات مفقودة / نسخ
 * جزئية / انحراف مزامنة. عند اكتشاف نقص يُعلِّم الأصل failed (وللأصول ذات نسخة
 * محلّية: stored_remote=false كي تُعاد مزامنتها لاحقاً عبر media:sync:remote).
 *
 * resumable · دفعات · للقراءة (HEAD) فقط عدا تعليم الانحراف · بلا كسر روابط.
 */
class VerifyRemoteMediaCommand extends Command
{
    /** @var array<int,string> */
    private const LOCAL_DISKS = ['uploads', 'public', 'local'];

    protected $signature = 'media:verify:remote {--limit=1000 : أقصى عدد أصول لكل تشغيل} {--deep : افحص كل المشتقّات لا الأصل فقط}';

    protected $description = 'Verify remote integrity for mirrored assets and flag drift.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $deep = (bool) $this->option('deep');
        $checked = 0;
        $drift = 0;

        MediaAsset::query()
            ->library()
            ->where('stored_remote', true)
            ->where('remote_sync_status', 'synced')
            ->where('kind', '!=', 'external')
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$checked, &$drift, $limit, $deep): bool {
                foreach ($chunk as $asset) {
                    if ($checked >= $limit) {
                        return false;
                    }
                    $checked++;
                    if (! $this->remoteIntact($asset, $deep)) {
                        $this->flagDrift($asset);
                        $drift++;
                    }
                }

                return true;
            });

        $this->info("Verified {$checked} asset(s), drift detected: {$drift}.");

        return self::SUCCESS;
    }

    private function remoteIntact(MediaAsset $asset, bool $deep): bool
    {
        $remoteName = in_array($asset->disk, self::LOCAL_DISKS, true)
            ? RemoteStorage::diskName()
            : $asset->disk;

        try {
            $remote = Storage::disk($remoteName);
        } catch (Throwable) {
            return false;
        }

        $primary = $asset->remote_path ?? $asset->path;
        if (! $remote->exists($primary)) {
            return false;
        }

        if (! $deep) {
            return true;
        }

        // فحص عميق: كل ملفات الشجرة المحلّية موجودة بعيداً.
        if (! $asset->stored_local) {
            return true; // لا مرجع محلّي للمقارنة
        }
        $local = Storage::disk($asset->disk);
        $prefix = trim(dirname($asset->path), '/.');
        foreach (($prefix !== '' ? $local->allFiles($prefix) : [$asset->path]) as $key) {
            if (! $remote->exists($key)) {
                return false;
            }
        }

        return true;
    }

    private function flagDrift(MediaAsset $asset): void
    {
        $asset->forceFill([
            'remote_sync_status' => 'failed',
            // ذات نسخة محلّية ⇒ مرشّحة لإعادة المزامنة؛ بعيدة فقط ⇒ تبقى معلَّمة.
            'stored_remote' => ! $asset->stored_local,
            'remote_sync_error' => 'verify: remote object missing',
        ])->save();
    }
}
