<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Broadcast;

use App\Http\Requests\BaseFormRequest;

/**
 * نبضة الحضور — تحمل رمز الجلسة الموقّع الصادر عن /join. التحقّق من التوقيع/الانتهاء
 * يجري في PresenceSessionToken داخل الـ Action (لا في القواعد هنا).
 */
class PresenceHeartbeatRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
