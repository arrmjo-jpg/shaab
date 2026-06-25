<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class MoveCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', Rule::in(['up', 'down'])],
        ];
    }
}
