<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Chat;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['direct', 'group'])],
            // المباشرة: مستخدم واحد آخر. المجموعة: واحد فأكثر.
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            // العنوان مطلوب للمجموعة فقط.
            'title' => ['required_if:type,group', 'nullable', 'string', 'max:150'],
        ];
    }
}
