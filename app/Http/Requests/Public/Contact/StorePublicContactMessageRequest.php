<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Contact;

use App\Enums\ContactMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء رسالة «اتصل بنا» (عام) — تحقّق الحقول فقط. حماية reCAPTCHA + throttle على المسار
 * (middleware)، لا داخل الـRequest. النوع ضمن allow-list من ContactMessageType.
 */
class StorePublicContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:200'],
            'type' => ['required', 'string', Rule::in(ContactMessageType::values())],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
