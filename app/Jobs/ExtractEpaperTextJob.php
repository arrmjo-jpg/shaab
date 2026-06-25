<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Admin\Epaper\ExtractEpaperTextAction;
use App\Enums\EpaperOcrStatus;
use App\Models\Epaper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * استخراج نصّ العدد (OCR) خارج دورة الطلب — queue=media (Phase 4a). فريد لكل عدد
 * (ShouldBeUnique) فلا يزدوج تحت إعادة المحاولة/إعادة الجدولة. يحمل المُعرّف فقط
 * فيُعاد تحميل العدد طازجاً عند التنفيذ. دورة الحالة في ExtractEpaperTextAction.
 */
class ExtractEpaperTextJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(private readonly int $epaperId)
    {
        $this->onQueue((string) config('epaper.ocr.queue', 'media'));
        $this->onConnection(config('queue.media_connection'));
    }

    /**
     * نقطة الدخول الموحّدة لبدء OCR لعددٍ ما: يضبط الحالة pending ويُصفِّر ميتاداتا
     * الوثيقة (تُعاد عند الاستخراج) ثمّ يُجدوِل الوظيفة. يُستخدَم عند الإنشاء/الاستبدال/
     * إعادة المعالجة — مصدر واحد لمنطق «ابدأ الاستخراج».
     */
    public static function enqueue(Epaper $epaper): void
    {
        $epaper->forceFill([
            'ocr_status' => EpaperOcrStatus::Pending->value,
            'text_layer' => null,
            'page_count' => null,
        ])->save();

        self::dispatch($epaper->id);
    }

    public function uniqueId(): string
    {
        return 'epaper-ocr-'.$this->epaperId;
    }

    public function handle(ExtractEpaperTextAction $action): void
    {
        $action->handle($this->epaperId);
    }

    public function failed(?Throwable $e): void
    {
        // فشل على مستوى الوظيفة (بعد استنفاد المحاولات) — وسم العدد failed بلا أحداث.
        Epaper::query()->whereKey($this->epaperId)
            ->update(['ocr_status' => EpaperOcrStatus::Failed->value]);
    }
}
