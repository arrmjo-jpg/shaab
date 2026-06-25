<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Http\Requests\BaseFormRequest;
use App\Models\WhatsappGroup;
use Illuminate\Validation\Rule;

class UpdateWhatsappGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $group = $this->route('whatsappGroup');
        $id = $group instanceof WhatsappGroup ? $group->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150',
                Rule::unique('whatsapp_groups', 'name')->ignore($id)->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
