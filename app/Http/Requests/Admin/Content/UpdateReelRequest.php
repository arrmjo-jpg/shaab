<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use App\Models\Reel;
use Illuminate\Validation\Rule;

class UpdateReelRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reel = $this->route('reel');
        $locale = $this->input('locale', $reel?->locale);

        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'locale' => ['sometimes', 'string', Rule::in(Reel::LOCALES)],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'media_asset_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('reels', 'slug')
                    ->where(fn ($q) => $q->where('locale', $locale))
                    ->ignore($reel?->getKey()),
            ],

            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_featured' => ['sometimes', 'boolean'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
