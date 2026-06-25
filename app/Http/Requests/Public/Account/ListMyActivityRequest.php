<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Account;

use App\Http\Requests\BaseFormRequest;
use App\Support\Engagement\EngageableResolver;
use Illuminate\Validation\Rule;

/**
 * تحقّق User Activity API (قراءة-فقط). المسار محميّ مسبقاً (auth:sanctum + abilities:user)
 * فالتفويض true. التحقّق على معاملات الاستعلام (GET).
 */
class ListMyActivityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            // الأنشطة المدعومة الآن (مصدرها جدول engagements). مستقبلاً تُضاف 'history'|'continue'
            // إلى هذه القائمة + خريطة المصدر في الـ Action — دون أيّ نقطة جديدة.
            'activity' => ['required', Rule::in(['liked', 'saved'])],
            // أيّ نوع قابل للتفاعل (يتوسّع تلقائيّاً مع EngageableResolver::MAP)؛ غيابه = الكل.
            'content_type' => ['nullable', Rule::in(EngageableResolver::types())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
