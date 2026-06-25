<?php

declare(strict_types=1);

namespace App\Support\Epaper\Ocr;

/**
 * عقد مزوّد استخراج نصّ العدد. يُحلّ من الحاوية (قابل لإعادة الربط من المضيف وللـ
 * mock في الاختبارات). يجب ألّا يرمي عند «لا يوجد نصّ» — يرمي فقط عند خطأ صلب
 * (تعذّر الاتصال/الاعتماد) كي يُسجِّله الخطّ failed.
 */
interface EpaperOcrProvider
{
    /** يستخرج نصّ كل صفحة من ملفّ PDF محلّي. */
    public function extract(string $pdfPath): OcrExtraction;
}
