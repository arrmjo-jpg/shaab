<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/** عدّ المستلمين المتوقَّع لمجموعات مختارة — قبل حفظ/إرسال الحملة. */
class CountWhatsappRecipientsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer', Rule::exists('whatsapp_groups', 'id')->whereNull('deleted_at')],
        ];
    }
}
