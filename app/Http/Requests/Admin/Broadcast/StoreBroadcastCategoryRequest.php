<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء تصنيف بثّ مسطّح (لا parent/locale). slug فريد عام.
 */
class StoreBroadcastCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:160',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('broadcast_categories', 'slug'),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'cover_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
