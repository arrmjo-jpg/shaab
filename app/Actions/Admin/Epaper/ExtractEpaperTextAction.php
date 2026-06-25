<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Enums\EpaperOcrStatus;
use App\Enums\EpaperTextLayer;
use App\Models\Epaper;
use App\Models\EpaperPage;
use App\Support\Epaper\EpaperSearchIndexer;
use App\Support\Epaper\Ocr\EpaperOcrProvider;
use App\Support\Epaper\Ocr\OcrExtraction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * خطّ استخراج نصّ العدد (OCR — Phase 4a). يُنفَّذ خارج دورة الطلب عبر
 * ExtractEpaperTextJob (لا HTTP هنا)، ويُستدعى مباشرةً في الاختبارات.
 *
 * دورة الحالة: pending (عند الجدولة) → processing → done | partial | failed.
 *   - done/present : كل الصفحات بها نصّ.
 *   - partial      : بعض الصفحات بها نصّ.
 *   - done/absent  : لا نصّ (بنية الصفحات معروفة — وثيقة ممسوحة بلا تصعيد AI).
 *   - failed       : تعذّر الاستخراج (لا أداة/لا وثيقة محلّية/خطأ).
 *
 * idempotent: يُعيد بناء صفحات العدد كلياً (حذف ثمّ إدراج داخل معاملة) فإعادة
 * التنفيذ تُنتج الحالة ذاتها. المزوّد يُحلّ من الحاوية (قابل للـ mock).
 */
class ExtractEpaperTextAction
{
    public function __construct(private readonly EpaperOcrProvider $provider) {}

    public function handle(int $epaperId): void
    {
        $epaper = Epaper::query()->find($epaperId);
        if ($epaper === null) {
            return; // حُذف بين الجدولة والتنفيذ
        }

        $epaper->forceFill(['ocr_status' => EpaperOcrStatus::Processing->value])->save();

        $tmp = null;
        try {
            $tmp = $this->materializePdf($epaper);
            if ($tmp === null) {
                $this->markFailed($epaper, 'no_local_document');

                return;
            }

            $this->store($epaper, $this->provider->extract($tmp));
        } catch (Throwable $e) {
            Log::warning('epaper.ocr.failed', ['epaper_id' => $epaper->id, 'error' => $e->getMessage()]);
            $this->markFailed($epaper, 'extraction_error');
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
            // تغيّرت صفحات العدد (أو فشل الاستخراج) ⇒ زامِن فهرس البحث ليطابق المصدر.
            EpaperSearchIndexer::queueSync($epaper->id);
        }
    }

    /** يخزّن الصفحات (إعادة بناء كاملة) ويحسم حالة OCR + طبقة النصّ. */
    private function store(Epaper $epaper, OcrExtraction $extraction): void
    {
        $pageCount = $extraction->pageCount();

        // لا بنية صفحات (لا أداة/تعذّر صلب) ⇒ failed دون تخزين.
        if ($pageCount === 0) {
            $this->markFailed($epaper, 'no_pages');

            return;
        }

        DB::transaction(function () use ($epaper, $extraction): void {
            EpaperPage::query()->where('epaper_id', $epaper->id)->delete();
            foreach ($extraction->pages as $number => $text) {
                EpaperPage::query()->create([
                    'epaper_id' => $epaper->id,
                    'page_number' => $number,
                    'text' => $text,
                    'source' => $extraction->source,
                    'has_text' => trim($text) !== '',
                ]);
            }
        });

        $nonEmpty = $extraction->nonEmptyCount();
        [$status, $layer] = match (true) {
            $nonEmpty === $pageCount => [EpaperOcrStatus::Done, EpaperTextLayer::Present],
            $nonEmpty === 0 => [EpaperOcrStatus::Done, EpaperTextLayer::Absent],
            default => [EpaperOcrStatus::Partial, EpaperTextLayer::Partial],
        };

        $epaper->forceFill([
            'ocr_status' => $status->value,
            'text_layer' => $layer->value,
            'page_count' => $pageCount,
        ])->save();
    }

    /**
     * ينسخ PDF العدد إلى ملفّ محلّي مؤقّت (يدعم القرص المحلّي والبعيد على السواء).
     * يُعيد null إن تعذّر (لا أصل / أصل خارجيّ بلا ملفّ / مفقود على القرص).
     */
    private function materializePdf(Epaper $epaper): ?string
    {
        $asset = $epaper->mediaAsset;
        if ($asset === null || $asset->path === '') {
            return null;
        }

        $disk = Storage::disk($asset->disk);
        if (! $disk->exists($asset->path)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'epocr_');
        if ($tmp === false) {
            return null;
        }
        file_put_contents($tmp, $disk->get($asset->path));

        return $tmp;
    }

    private function markFailed(Epaper $epaper, string $reason): void
    {
        Log::info('epaper.ocr.unprocessed', ['epaper_id' => $epaper->id, 'reason' => $reason]);
        $epaper->forceFill(['ocr_status' => EpaperOcrStatus::Failed->value])->save();
    }
}
