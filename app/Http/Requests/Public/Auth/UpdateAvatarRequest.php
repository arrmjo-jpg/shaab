<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Auth;

use App\Http\Requests\BaseFormRequest;

class UpdateAvatarRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
