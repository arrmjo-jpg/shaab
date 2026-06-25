<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Enums\EngagementType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ReactRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => [
                'required',
                Rule::in([EngagementType::Like->value, EngagementType::Dislike->value]),
            ],
        ];
    }
}
