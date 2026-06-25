<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use App\Models\Page;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $locale = $this->input('locale', $page?->locale);

        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'locale' => ['sometimes', 'string', Rule::in(Page::LOCALES)],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('pages', 'slug')
                    ->where(fn ($q) => $q->where('locale', $locale))
                    ->ignore($page?->getKey()),
            ],

            'content' => ['sometimes', 'nullable', 'string', 'max:200000'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            'template' => ['sometimes', 'nullable', 'string', 'max:100'],
            'show_in_header' => ['sometimes', 'boolean'],
            'show_in_footer' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
