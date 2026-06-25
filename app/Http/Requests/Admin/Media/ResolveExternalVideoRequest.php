<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Media;

use App\Http\Requests\BaseFormRequest;

class ResolveExternalVideoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }
}
