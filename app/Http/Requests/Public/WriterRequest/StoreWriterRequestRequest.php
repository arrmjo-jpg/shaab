<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\WriterRequest;

use App\Http\Requests\BaseFormRequest;

class StoreWriterRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
