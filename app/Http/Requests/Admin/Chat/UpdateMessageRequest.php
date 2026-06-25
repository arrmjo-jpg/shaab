<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Chat;

use App\Http\Requests\BaseFormRequest;

class UpdateMessageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
