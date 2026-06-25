<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Media;

use App\Http\Requests\BaseFormRequest;

/**
 * تعديل البيانات الوصفية التحريرية لأصل مكتبة (لا الملف نفسه).
 */
class UpdateMediaAssetRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
