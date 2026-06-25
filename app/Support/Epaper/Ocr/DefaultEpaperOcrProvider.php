<?php

declare(strict_types=1);

namespace App\Support\Epaper\Ocr;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * المزوّد الافتراضيّ (المركّب): يفضّل النصّ المضمَّن (تكلفة صفرية) — إن أنتج أيّ نصّ
 * اعتُمد. وإلا، وفقط إن فُعِّل Google Document AI، يُصعِّد إليه. فشل الـ AI لا يُسقط
 * الخطّ (fail-safe): يُسجَّل ويُرجَع ناتج المضمَّن (قد يكون فارغاً ⇒ يحدّد الخطّ
 * absent/failed). المضيف حرّ بإعادة ربط EpaperOcrProvider لمزوّد مخصّص.
 */
final class DefaultEpaperOcrProvider implements EpaperOcrProvider
{
    public function __construct(
        private readonly EmbeddedPdfTextProvider $embedded,
        private readonly GoogleDocumentAiProvider $google,
    ) {}

    public function extract(string $pdfPath): OcrExtraction
    {
        $embedded = $this->embedded->extract($pdfPath);

        // تفضيل المضمَّن: أيّ نصّ مضمَّن ⇒ اعتمده بلا تكلفة AI.
        if ($embedded->hasAnyText()) {
            return $embedded;
        }

        // لا نصّ مضمَّن (وثيقة ممسوحة غالباً) ⇒ صعِّد إلى Document AI إن فُعِّل فقط.
        if ($this->googleEnabled()) {
            try {
                $ai = $this->google->extract($pdfPath);
                if ($ai->hasAnyText()) {
                    return $ai;
                }
            } catch (Throwable $e) {
                Log::warning('epaper.ocr.google_failed', ['error' => $e->getMessage()]);
            }
        }

        // المضمَّن دون نصّ (بنية صفحات معروفة ⇒ absent) أو فارغ تماماً (⇒ failed).
        return $embedded;
    }

    private function googleEnabled(): bool
    {
        return (bool) config('epaper.ocr.google.enabled', false)
            && (string) config('epaper.ocr.google.processor_id', '') !== '';
    }
}
