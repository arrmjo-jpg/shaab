<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Whatsapp;

use Illuminate\Foundation\Http\FormRequest;

/**
 * اشتراك عامّ من الموقع — الاسم + الهاتف فقط (لا حقول أخرى). التطبيع/التحقّق E.164 ومنع
 * التكرار في الـ Action. الحماية (throttle) على المسار. phone نصّ خام هنا.
 */
class SubscribeWhatsappRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'max:25'],
        ];
    }
}
