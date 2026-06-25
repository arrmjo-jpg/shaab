<?php

declare(strict_types=1);

namespace App\Support\Epaper\Ocr;

/**
 * نتيجة استخراج نصّ من وثيقة PDF: نصّ لكل صفحة (مفهرس 1-أساسي) + مصدر الاستخراج.
 * كائن قيمة غير قابل للتغيير — لا منطق تخزين/حالة هنا (يتولّاه خطّ الاستخراج).
 */
final class OcrExtraction
{
    /**
     * @param  array<int,string>  $pages  رقم الصفحة (1-أساسي) ⇒ نصّها (قد يكون فارغاً)
     * @param  string  $source  embedded | google_document_ai | none
     */
    public function __construct(
        public readonly array $pages,
        public readonly string $source,
    ) {}

    public static function empty(string $source = 'none'): self
    {
        return new self([], $source);
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function nonEmptyCount(): int
    {
        return count(array_filter($this->pages, static fn (string $t): bool => trim($t) !== ''));
    }

    /** هل أُنتِج أيّ نصّ غير فارغ؟ (يحكم تفضيل المضمَّن/التصعيد للـ AI). */
    public function hasAnyText(): bool
    {
        return $this->nonEmptyCount() > 0;
    }

    /** هل كل صفحة بها نصّ؟ (تغطية كاملة ⇒ done/present). */
    public function isFullyCovered(): bool
    {
        return $this->pageCount() > 0 && $this->nonEmptyCount() === $this->pageCount();
    }
}
