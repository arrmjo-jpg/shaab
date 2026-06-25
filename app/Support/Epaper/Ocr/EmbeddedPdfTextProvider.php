<?php

declare(strict_types=1);

namespace App\Support\Epaper\Ocr;

use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * مستخرِج النصّ المضمَّن في الـ PDF عبر poppler «pdftotext» — المسار الافتراضيّ
 * بلا تكلفة. لا تحويل إلى صور (الوثيقة تبقى PDF). يفصل الصفحات بمحرف form-feed
 * (\f) الذي يبثّه poppler بين الصفحات. غياب الأداة/فشلها ⇒ نتيجة فارغة بلا رمي
 * (fail-safe) كي يقرّر المركّب التصعيد أو يسجّل الخطّ الحالة المناسبة.
 */
final class EmbeddedPdfTextProvider implements EpaperOcrProvider
{
    public function extract(string $pdfPath): OcrExtraction
    {
        $binary = (string) config('epaper.ocr.embedded.binary', 'pdftotext');
        $timeout = (int) config('epaper.ocr.embedded.timeout', 120);

        try {
            // -enc UTF-8: نصّ عربيّ سليم؛ -eol unix: فواصل أسطر موحّدة؛ "-": إلى stdout.
            $result = Process::timeout(max(1, $timeout))
                ->run([$binary, '-enc', 'UTF-8', '-eol', 'unix', $pdfPath, '-']);
        } catch (Throwable) {
            return OcrExtraction::empty('embedded'); // الأداة غير متاحة ⇒ فارغ
        }

        if (! $result->successful()) {
            return OcrExtraction::empty('embedded');
        }

        $output = $result->output();
        if ($output === '') {
            return OcrExtraction::empty('embedded'); // لا بنية صفحات ⇒ تعذّر الاستخراج
        }

        $chunks = explode("\f", $output);
        // poppler يُلحق form-feed بعد الصفحة الأخيرة ⇒ نُسقِط قطعة فارغة زائدة واحدة.
        if (count($chunks) > 1 && trim((string) end($chunks)) === '') {
            array_pop($chunks);
        }

        $pages = [];
        foreach (array_values($chunks) as $i => $text) {
            $pages[$i + 1] = trim((string) $text);
        }

        return new OcrExtraction($pages, 'embedded');
    }
}
