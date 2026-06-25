<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;

/** إرسال اختبار لرقم واحد — الرقم نصّ خام، يُطبَّع/يُتحقَّق E.164 في الـ Action. */
class TestWhatsappCampaignRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:25'],
        ];
    }
}
