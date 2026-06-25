<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappContactRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:25'],
            'groups' => ['sometimes', 'array', 'min:1'],
            'groups.*' => ['integer',
                Rule::exists('whatsapp_groups', 'id')->whereNull('deleted_at')],
        ];
    }
}
