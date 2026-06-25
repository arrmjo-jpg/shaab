<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Http\Requests\BaseFormRequest;

/**
 * استبدال ملف الـ PDF لعدد — يُنشئ نسخة جديدة (versioning). لا يغيّر الرابط العام
 * فلا يُسجَّل تحويل (قرار #2). الملف مطلوب وملاحظة اختيارية للسجلّ التدقيقيّ.
 */
class ReplacePdfRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (epapers.edit)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $pdfMaxKb = (int) config('performance.media.pdf_max_kb', 102400);

        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.$pdfMaxKb],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
