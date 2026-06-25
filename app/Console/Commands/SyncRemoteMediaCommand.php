<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MirrorMediaToRemoteJob;
use App\Models\MediaAsset;
use App\Support\Media\RemoteStorage;
use Illuminate\Console\Command;

/**
 * مزامنة المتراكم التشغيلي فقط (drift recovery): أصول محلّية أحدث لم تُنسَخ بعد
 * (مثلاً: عُطّل البعيد مؤقتاً واستمرّ الرفع محلّياً ثم أُعيد تفعيله).
 *
 * ليست للهجرة الأولى الضخمة (وسائط WordPress) — تلك تُنقَل يدوياً خارج التطبيق.
 *
 * resumable (الحالة synced تُستبعَد) · دفعات (chunkById) · آمن للذاكرة/الطابور ·
 * بلا عمل مكرّر (الوظيفة idempotent + ShouldBeUnique).
 */
class SyncRemoteMediaCommand extends Command
{
    protected $signature = 'media:sync:remote {--limit=500 : أقصى عدد أصول لكل تشغيل}';

    protected $description = 'Dispatch mirror jobs for operational backlog (local-only, unsynced) assets.';

    public function handle(): int
    {
        if (! RemoteStorage::enabled()) {
            $this->info('Remote storage disabled — nothing to sync.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dispatched = 0;

        MediaAsset::query()
            ->library()
            ->where('stored_local', true)
            ->where('stored_remote', false)
            ->whereIn('remote_sync_status', ['pending', 'failed'])
            ->where(fn ($q) => $q->whereNull('processing_status')->orWhere('processing_status', 'ready'))
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$dispatched, $limit): bool {
                foreach ($chunk as $asset) {
                    if ($dispatched >= $limit) {
                        return false; // أوقف الترقيم عند بلوغ الحدّ (resumable لاحقاً)
                    }
                    MirrorMediaToRemoteJob::dispatch($asset->id);
                    $dispatched++;
                }

                return true;
            });

        $this->info("Dispatched {$dispatched} mirror job(s).");

        return self::SUCCESS;
    }
}
