<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Settings\Media;

use App\Http\Requests\BaseFormRequest;

class UploadFirebaseMediaRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_account' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:256'],
        ];
    }
}
