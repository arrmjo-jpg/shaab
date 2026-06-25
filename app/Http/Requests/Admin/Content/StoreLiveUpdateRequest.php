<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use App\Rules\ValidTipTapDocument;
use Illuminate\Validation\Rule;

class StoreLiveUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            // P4-D1: نفس مصفاة TipTap الصارمة المستخدمة للمقالات
            'content_json' => ['required', 'array', new ValidTipTapDocument],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_breaking' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            // زمن الحدث اختياري — يُضبط للحظة الإنشاء إن غاب
            'happened_at' => ['sometimes', 'nullable', 'date'],

            // إسناد وسائط المكتبة المركزية (نفس بنية المقال — attach-on-save)
            'media' => ['sometimes', 'array'],
            'media.*.asset_id' => ['required', 'integer', 'distinct', 'exists:media_assets,id'],
            'media.*.collection' => ['required', Rule::in(['cover', 'gallery', 'inline', 'video'])],
            'media.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
