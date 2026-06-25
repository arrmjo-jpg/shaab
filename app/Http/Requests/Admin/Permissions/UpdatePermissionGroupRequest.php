<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Permissions;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionGroupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $groupId = $this->route('permissionGroup')->id;

        return [
            'slug' => [
                'sometimes', 'string', 'min:2', 'max:100',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('permission_groups', 'slug')->ignore($groupId),
            ],
            'display_name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
