<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * مزامنة فهرس بحث عددٍ (Meilisearch) خارج الطلب — طابور «search» المخصّص فلا يتنافس مع
 * الوسائط/الافتراضي. UniqueUntilProcessing: يُسقِط الازدواج المتراكم لكن يحرّر القفل عند
 * بدء التنفيذ، فأيّ تحديث يقع أثناء المزامنة يُجدوِل تشغيلاً جديداً يقرأ أحدث حالة (تقارب).
 *
 * يُعيد تحميل العدد طازجاً (مع المحذوف): موجود ⇒ إعادة فهرسة (تُطهّر إن لم يَعُد مؤهّلاً)؛
 * غير موجود (حذف نهائيّ) ⇒ إزالة. استثناءات نقل المحرّك تُعيد المحاولة (backoff تصاعديّ).
 */
class SyncEpaperSearchIndexJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** @var array<int,int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(private readonly int $epaperId)
    {
        $this->onQueue((string) config('epaper.search.queue', 'search'));
    }

    public function uniqueId(): string
    {
        return 'epaper-search-'.$this->epaperId;
    }

    public function handle(): void
    {
        if (! EpaperSearchIndexer::enabled()) {
            return;
        }

        $issue = Epaper::withTrashed()->find($this->epaperId);

        if ($issue === null) {
            EpaperSearchIndexer::removeIssue($this->epaperId);

            return;
        }

        EpaperSearchIndexer::reindexIssue($issue);
    }
}
