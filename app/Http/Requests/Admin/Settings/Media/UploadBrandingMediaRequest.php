<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings\Media;

use App\Http\Requests\BaseFormRequest;

class UploadBrandingMediaRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo_light' => ['required_without_all:logo_dark,favicon,watermark_image', 'file', 'image', 'mimes:png,webp,jpg,jpeg', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048'],
            'logo_dark' => ['sometimes', 'file', 'image', 'mimes:png,webp,jpg,jpeg', 'mimetypes:image/png,image/jpeg,image/webp', 'max:2048'],
            'favicon' => ['sometimes', 'file', 'mimes:ico,png', 'mimetypes:image/png,image/vnd.microsoft.icon,image/x-icon', 'max:512'],
            'watermark_image' => ['sometimes', 'file', 'image', 'mimes:png,webp', 'mimetypes:image/png,image/webp', 'max:2048'],
        ];
    }
}
