<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdPacingMode;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء حملة — دائماً مسودّة (الحالة لا تُقبل هنا؛ النشر = انتقال محروس draft→scheduled بعد إضافة
 * إبداع وربطه بمساحة). budget_spent يُدار نظامياً. حقول الميزانية/الوتيرة/الاستهداف مُخزَّنة
 * كـ«جاهزة-مستقبلاً» دون محرّك في هذه المرحلة.
 */
class StoreAdCampaignRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'advertiser_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'budget_total' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pacing_mode' => ['sometimes', Rule::in(AdPacingMode::values())],
            'targeting' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
