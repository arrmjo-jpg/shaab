<?php

declare(strict_types=1);

namespace App\Modules\CDN\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class PurgeUrlsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'urls' => ['required', 'array', 'min:1', 'max:100'],
            'urls.*' => ['required', 'url', 'max:2048'],
        ];
    }
}
