<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Enums\EpaperAccessLevel;
use App\Http\Requests\BaseFormRequest;
use App\Models\Epaper;
use Illuminate\Validation\Rule;

/**
 * تعديل بيانات عدد (ميتاداتا فقط — استبدال الـ PDF عبر نقطة نهاية منفصلة). تغيّر
 * الـ slug صراحةً يُسجّل تحويلاً في الـ Action (الرابط العام تغيّر فعلاً، قرار #2).
 */
class UpdateEpaperRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (epapers.edit)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        /** @var Epaper $epaper */
        $epaper = $this->route('epaper');
        $locale = $epaper->locale;

        return [
            'issue_number' => [
                'sometimes', 'integer', 'min:1',
                Rule::unique('epapers', 'issue_number')->where(fn ($q) => $q->where('locale', $locale))->ignore($epaper->id),
            ],
            'title' => ['sometimes', 'string', 'max:190'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:190'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            // حقول تحريريّة منتقاة (اختياريّة) — تُغذّي النشرة/المختارات/الفهرس في الواجهة.
            'brief_points' => ['sometimes', 'nullable', 'array'],
            'brief_points.*.title' => ['required_with:brief_points', 'string', 'max:300'],
            'brief_points.*.why' => ['nullable', 'string', 'max:500'],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*.title' => ['required_with:highlights', 'string', 'max:300'],
            'highlights.*.quote' => ['nullable', 'string', 'max:500'],
            'highlights.*.page' => ['nullable', 'integer', 'min:1'],
            'inside_this_issue' => ['sometimes', 'nullable', 'array'],
            'inside_this_issue.*.label' => ['required_with:inside_this_issue', 'string', 'max:120'],
            'inside_this_issue.*.lead' => ['nullable', 'string', 'max:300'],
            'inside_this_issue.*.page' => ['nullable', 'integer', 'min:1'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:180',
                Rule::unique('epapers', 'slug')->where(fn ($q) => $q->where('locale', $locale))->ignore($epaper->id),
            ],
            'publication_date' => ['sometimes', 'date'],
            'access_level' => ['sometimes', Rule::enum(EpaperAccessLevel::class)],
        ];
    }
}
