<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Polls;

use App\Enums\PollAudienceMode;
use App\Enums\PollResultVisibility;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء استطلاع. is_active لا يُقبل هنا — التفعيل إجراء نشر مستقلّ (polls.publish)؛ يُنشأ
 * الاستطلاع معطّلاً دائماً. الخياران اثنان على الأقل. التفويض عبر permission middleware.
 */
class StorePollRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'audience_mode' => ['sometimes', Rule::in(PollAudienceMode::values())],
            'result_visibility' => ['sometimes', Rule::in(PollResultVisibility::values())],
            'options' => ['required', 'array', 'min:2'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
