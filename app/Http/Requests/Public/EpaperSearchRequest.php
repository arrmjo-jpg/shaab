<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use App\Support\Epaper\EpaperPageSearch;
use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق بحث «داخل العدد» (عامّ). q إلزاميّ (2–100 محرف) لتفادي الضجيج والكشط؛
 * per_page/page اختياريّان بحدود. الوصول/الوحدة يفرضهما المسار والمتحكّم.
 */
class EpaperSearchRequest extends FormRequest
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
        ];
    }
}
