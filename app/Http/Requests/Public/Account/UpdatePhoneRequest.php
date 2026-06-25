<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Account;

use App\Http\Requests\BaseFormRequest;

/**
 * نافذة ما بعد الدخول — حفظ رقم الهاتف + اختيار الاشتراك في حملات واتساب. الرقم نصّ خام هنا؛
 * التطبيع/التحقّق E.164 يتمّ في الـ Action (نفس عقد SubscribeWhatsappRequest العامّ).
 */
class UpdatePhoneRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:25'],
            'whatsapp_subscribed' => ['sometimes', 'boolean'],
        ];
    }
}
