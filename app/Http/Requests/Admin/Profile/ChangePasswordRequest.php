<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Profile;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
