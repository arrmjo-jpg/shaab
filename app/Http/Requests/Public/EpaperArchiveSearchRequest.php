<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Support\Epaper\EpaperPageSearch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق بحث الأرشيف العابر للأعداد (عامّ). q إلزاميّ (2–100 محرف) لتفادي الضجيج
 * والكشط؛ مرشّحات اختيارية: رقم العدد + مدى التاريخ (date_to ≥ date_from). الوصول
 * والوحدة يفرضهما المسار والمتحكّم (مستويات مرئيّة مُشتقّة من السياسة).
 */
class EpaperArchiveSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.EpaperPageSearch::MAX_PER_PAGE],
            'page' => ['sometimes', 'integer', 'min:1'],
            'issue_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
