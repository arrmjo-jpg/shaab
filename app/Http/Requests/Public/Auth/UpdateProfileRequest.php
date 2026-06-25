<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Auth;

use App\Http\Requests\BaseFormRequest;

class UpdateProfileRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'social_links' => ['sometimes', 'nullable', 'array'],
            'social_links.*' => ['nullable', 'string', 'url', 'max:255'],
        ];
    }
}
