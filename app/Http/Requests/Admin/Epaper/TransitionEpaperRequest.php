<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Enums\EpaperStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * انتقال حالة العدد (draft/scheduled/published/archived). published_at لازم
 * (ومستقبليّ) للجدولة — يُفرَض في الـ Action. الصلاحيات (publish/archive) في الـ Action.
 */
class TransitionEpaperRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // صلاحية الوصول عبر middleware المسار (epapers.edit)؛ صلاحية الانتقال في الـ Action
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(EpaperStatus::class)],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
