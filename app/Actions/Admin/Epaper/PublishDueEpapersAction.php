<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Enums\EpaperStatus;
use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * نشر الأعداد المُجدوَلة المستحقّة (published_at <= now) — حتميّ، مقفول، idempotent.
 * قفل توزيع يمنع التنفيذ المتزامن؛ lockForUpdate لكل صفّ يمنع النشر المزدوج؛ يتحقّق
 * من بقاء الشروط (مجدوَل + مستحقّ + له PDF) قبل النشر. مرآة PublishDueVideosAction.
 */
final class PublishDueEpapersAction
{
    private const LOCK_KEY = 'epapers:publish-due';

    public function handle(): int
    {
        $lock = Cache::lock(self::LOCK_KEY, 110);
        if (! $lock->get()) {
            return 0;
        }

        $published = 0;

        try {
            Epaper::query()
                ->where('status', EpaperStatus::Scheduled->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderBy('id')
                ->chunkById(100, function ($chunk) use (&$published): void {
                    foreach ($chunk as $epaper) {
                        $ok = DB::transaction(function () use ($epaper): bool {
                            $fresh = Epaper::query()->whereKey($epaper->id)->lockForUpdate()->first();

                            if ($fresh === null
                                || $fresh->status !== EpaperStatus::Scheduled
                                || $fresh->published_at === null
                                || $fresh->published_at->isFuture()
                                || $fresh->media_asset_id === null) {
                                return false;
                            }

                            $fresh->status = EpaperStatus::Published->value;
                            $fresh->save();

                            return true;
                        });

                        if ($ok) {
                            $published++;
                            EpaperSearchIndexer::queueSync($epaper->id); // نُشِر آلياً ⇒ فهرسة
                        }
                    }
                });
        } finally {
            $lock->release();
        }

        return $published;
    }
}
