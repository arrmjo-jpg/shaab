<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Auth;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Password;

class AdminResetPasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            // إعادة تعيين كلمة مرور الإداري تتطلّب السياسة الإدارية الكاملة
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }
}
