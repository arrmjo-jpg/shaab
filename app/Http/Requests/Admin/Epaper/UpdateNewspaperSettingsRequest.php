<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Http\Requests\BaseFormRequest;

/**
 * تحديث إعدادات الجريدة الرقمية. الصلاحية (settings.edit) مفروضة عبر middleware المسار.
 */
class UpdateNewspaperSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (settings.edit)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'display_name' => ['required', 'string', 'max:100'],
            'subscribe_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
