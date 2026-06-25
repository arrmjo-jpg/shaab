<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contact;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ردّ الإدارة على رسالة اتصال — تحقّق المتن فقط (الصلاحية contact-messages.reply على المسار).
 */
class ReplyContactMessageRequest extends FormRequest
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
