<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Polls;

use App\Http\Requests\BaseFormRequest;

/**
 * تصويت عام. تحقّق بنيويّ فقط (مصفوفة معرّفات خيارات صحيحة)؛ قواعد المجال (الفتح،
 * الجمهور، العدد المسموح، انتماء الخيارات، منع التكرار) تُفرَض في CastVoteAction. عام (لا تفويض).
 */
class CastVoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'option_ids' => ['required', 'array', 'min:1', 'max:50'],
            'option_ids.*' => ['integer'],
        ];
    }
}
