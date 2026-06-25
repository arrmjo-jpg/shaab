<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\CategoryStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCategoriesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'exists:categories,id'],
            'status' => ['sometimes', Rule::in(CategoryStatus::values())],
            'show_in_header' => ['sometimes', 'boolean'],
            'show_in_body' => ['sometimes', 'boolean'],
            'show_in_footer' => ['sometimes', 'boolean'],
        ];
    }
}
