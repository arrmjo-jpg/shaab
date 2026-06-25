<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Http\Requests\BaseFormRequest;

class AnalyzeSeoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:300'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200'],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'max:50'],
            'locale' => ['sometimes', 'nullable', 'string', 'in:ar,en'],
        ];
    }
}
