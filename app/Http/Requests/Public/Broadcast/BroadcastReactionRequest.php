<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Broadcast;

use App\Enums\EngagementType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * تفاعل البثّ — like أو dislike فقط (تفاعل أحادي حصري). لا مفضّلة ولا تعليقات في هذا
 * النطاق. المُصادَقة يفرضها الـ middleware (auth)؛ الزوّار لا يتفاعلون.
 */
class BroadcastReactionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => ['required', Rule::in([EngagementType::Like->value, EngagementType::Dislike->value])],
        ];
    }
}
