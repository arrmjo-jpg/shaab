<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Permissions;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePermissionGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required', 'string', 'min:2', 'max:100',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('permission_groups', 'slug'),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
