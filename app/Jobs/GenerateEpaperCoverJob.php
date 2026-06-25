<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Epaper;
use App\Support\Epaper\EpaperCoverGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * توليد غلاف العدد خارج دورة الطلب (ثقيل نسبياً — قرص + poppler). يُعزَل على طابور
 * الوسائط (config epaper.cover.queue). idempotent: يعيد بناء الغلاف من الصفحة 1.
 */
class GenerateEpaperCoverJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public readonly int $epaperId)
    {
        $this->onQueue((string) config('epaper.cover.queue', 'media'));
    }

    /** يُجدوِل توليد الغلاف لعدد (يُستدعى بعد إنشاء/استبدال الـ PDF). */
    public static function enqueue(Epaper $epaper): void
    {
        self::dispatch($epaper->id)->afterCommit();
    }

    public function handle(EpaperCoverGenerator $generator): void
    {
        $epaper = Epaper::query()->find($this->epaperId);
        if ($epaper === null) {
            return;
        }
        $generator->generate($epaper);
    }
}
