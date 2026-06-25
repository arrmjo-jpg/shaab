<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\TeamMemberStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreTeamMemberRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'job_title' => ['required', 'string', 'min:2', 'max:150'],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],

            // slug يدوي اختياري — أحرف يونيكود (تشمل العربية)، فريد عالمياً.
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('team_members', 'slug'),
            ],

            'bio' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'avatar_asset_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],

            // روابط التواصل — مفاتيح مهيكَلة، كلّ قيمة URL صالح.
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['nullable', 'url', 'max:255'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            'status' => ['sometimes', Rule::in(TeamMemberStatus::values())],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
