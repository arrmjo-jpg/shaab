<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Whatsapp;

use Illuminate\Foundation\Http\FormRequest;

/** إلغاء اشتراك عامّ — عبر توكن سرّيّ يخصّ جهة الاتصال (لا تعداد، لا كشف رقم). */
class UnsubscribeWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:48'],
        ];
    }
}
