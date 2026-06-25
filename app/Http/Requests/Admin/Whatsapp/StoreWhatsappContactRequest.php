<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء جهة اتصال — phone نصّ خام هنا؛ التطبيع والتحقّق الدولي E.164 ومنع التكرار
 * في الـ Action (PhoneNumber — مصدر واحد للرسالة والتوحيد).
 */
class StoreWhatsappContactRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'max:25'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer',
                Rule::exists('whatsapp_groups', 'id')->whereNull('deleted_at')],
        ];
    }
}
