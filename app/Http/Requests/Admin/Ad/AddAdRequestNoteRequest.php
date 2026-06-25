<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ad;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إضافة ملاحظة داخليّة لطلب إعلان — تحقّق المتن فقط (تُضاف كسجلّ جديد، لا كتابة فوق سابق).
 */
class AddAdRequestNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }
}
