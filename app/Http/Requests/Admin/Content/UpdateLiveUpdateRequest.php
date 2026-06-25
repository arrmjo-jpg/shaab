<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use App\Rules\ValidTipTapDocument;
use Illuminate\Validation\Rule;

class UpdateLiveUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'content_json' => ['sometimes', 'array', new ValidTipTapDocument],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_breaking' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'happened_at' => ['sometimes', 'nullable', 'date'],

            // إسناد وسائط المكتبة المركزية (وجود المفتاح = مزامنة كاملة)
            'media' => ['sometimes', 'array'],
            'media.*.asset_id' => ['required', 'integer', 'distinct', 'exists:media_assets,id'],
            'media.*.collection' => ['required', Rule::in(['cover', 'gallery', 'inline', 'video'])],
            'media.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
