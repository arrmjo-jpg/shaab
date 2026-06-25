<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\PageStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TransitionPageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(PageStatus::values())],
        ];
    }
}
