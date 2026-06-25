<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings;

use App\Http\Requests\BaseFormRequest;

class UpdateCdnSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cdn_enabled' => ['sometimes', 'boolean'],
            'cdn_auto_purge' => ['sometimes', 'boolean'],
            'cdn_plan' => ['sometimes', 'string', 'in:free,pro,business,enterprise'],
            'cdn_api_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cdn_zone_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
