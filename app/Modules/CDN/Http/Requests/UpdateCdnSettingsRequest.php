<?php

declare(strict_types=1);

namespace App\Modules\CDN\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Modules\CDN\Enums\CdnPlan;
use Illuminate\Validation\Rule;

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
            'cdn_plan' => ['sometimes', Rule::enum(CdnPlan::class)],
            'cdn_api_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cdn_zone_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
