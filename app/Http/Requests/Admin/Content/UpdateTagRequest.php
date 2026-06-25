<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إعادة تسمية وسم — تحقّق فقط (الصلاحية tags.edit عبر middleware المسار).
 * الاسم خريطة locale→نص؛ يجب وجود لغة واحدة على الأقل (ar أو en).
 */
class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.ar' => ['nullable', 'string', 'max:60', 'required_without:name.en'],
            'name.en' => ['nullable', 'string', 'max:60', 'required_without:name.ar'],
        ];
    }
}
