<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Http\Requests\BaseFormRequest;
use App\Models\VideoCategory;
use Illuminate\Validation\Rule;

class UpdateVideoCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var VideoCategory $category */
        $category = $this->route('videoCategory');
        $locale = $this->input('locale', $category->locale);

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'locale' => ['sometimes', 'string', Rule::in(VideoCategory::LOCALES)],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:video_categories,id'],
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:160',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('video_categories', 'slug')->where(fn ($q) => $q->where('locale', $locale))->ignore($category->id),
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
