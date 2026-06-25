<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Http\Requests\BaseFormRequest;
use App\Support\Ai\AiEditorialService;
use Illuminate\Validation\Rule;

class RewriteTextRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:8000'],
            'mode' => ['required', 'string', Rule::in(AiEditorialService::REWRITE_MODES)],
            'locale' => ['sometimes', 'nullable', 'string', 'in:ar,en'],
        ];
    }
}
