<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdPlacementType;
use App\Enums\AdSelectorStrategy;
use App\Http\Requests\BaseFormRequest;
use App\Models\AdZone;
use Illuminate\Validation\Rule;

class UpdateAdZoneRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'key' => [
                'sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('ad_zones', 'key')->ignore($this->route('adZone')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'placement_type' => ['sometimes', Rule::in(AdPlacementType::values())],
            'selector_strategy' => ['sometimes', Rule::in(AdSelectorStrategy::values())],
            'width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5000'],
            'locale' => ['sometimes', 'nullable', Rule::in(AdZone::LOCALES)],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
