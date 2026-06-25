<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;

class UploadAuthorAvatarRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => [
                'required', 'file', 'image',
                'mimetypes:image/jpeg,image/png,image/webp', 'max:2048',
            ],
        ];
    }
}
