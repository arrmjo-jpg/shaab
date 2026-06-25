<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Scheduler;

use App\Http\Requests\BaseFormRequest;

class UpdateScheduledTaskRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // فقط enabled + notes — لا تعبير ولا أمر ولا مهام يعرّفها المستخدم
        return [
            'enabled' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
