<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Users;

use App\Http\Requests\BaseFormRequest;

class UploadUserAvatarRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }
}
