<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Enums\EpaperAccessLevel;
use App\Http\Requests\BaseFormRequest;
use App\Models\Epaper;
use Illuminate\Validation\Rule;

/**
 * إنشاء عدد رقميّ: الـ PDF مطلوب (mimetypes حقيقيّ). رقم العدد والـ slug فريدان
 * لكل لغة (شاملاً المحذوف منطقياً — يطابق الفهرس الفريد على المخطّط).
 */
class StoreEpaperRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (epapers.create)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $locale = (string) ($this->input('locale') ?: 'ar');
        $pdfMaxKb = (int) config('performance.media.pdf_max_kb', 102400);

        return [
            'issue_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('epapers', 'issue_number')->where(fn ($q) => $q->where('locale', $locale)),
            ],
            'title' => ['required', 'string', 'max:190'],
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
                Rule::unique('epapers', 'slug')->where(fn ($q) => $q->where('locale', $locale)),
            ],
            'publication_date' => ['required', 'date'],
            'access_level' => ['sometimes', Rule::enum(EpaperAccessLevel::class)],
            'locale' => ['sometimes', Rule::in(Epaper::LOCALES)],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.$pdfMaxKb],
        ];
    }
}
