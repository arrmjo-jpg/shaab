<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Http\Requests\BaseFormRequest;

class SuggestTagsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'min:20', 'max:20000'],
            'locale' => ['sometimes', 'nullable', 'string', 'in:ar,en'],
        ];
    }
}
