<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsappGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150',
                Rule::unique('whatsapp_groups', 'name')->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
