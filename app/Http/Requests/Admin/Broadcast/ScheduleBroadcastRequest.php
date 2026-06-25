<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

use App\Http\Requests\BaseFormRequest;

/**
 * جدولة بثّ — يتطلّب موعد بدء مستقبلي. شرعية الانتقال (draft → scheduled) تُفرَض
 * في الـ Action عبر BroadcastTransitionGuard.
 */
class ScheduleBroadcastRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
