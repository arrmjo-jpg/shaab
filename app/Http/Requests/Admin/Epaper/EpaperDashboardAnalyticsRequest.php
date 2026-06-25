<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحقّق مرشّحات لوحة تحليلات القارئ: المدى الزمنيّ (today/7d/30d/custom). عند custom
 * يلزم from/to (to ≥ from). الصلاحية (epapers.view) وبوابة الوحدة يفرضهما المسار.
 */
class EpaperDashboardAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,7d,30d,custom'],
            // بلا sometimes كي يعمل required_if عند غياب الحقل (sometimes يتخطّى القاعدة).
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
        ];
    }
}
