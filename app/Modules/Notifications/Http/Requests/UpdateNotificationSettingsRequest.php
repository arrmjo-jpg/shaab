<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * تحديث إعدادات مركز الإشعارات (Kill Switch + Quiet Hours). البوّابة على المسار
 * (permission:notifications.manage). لا أسرار في هذه المجموعة.
 */
final class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'critical_bypass' => ['required', 'boolean'],
            'quiet_hours_enabled' => ['required', 'boolean'],
            'quiet_hours_start' => ['required', 'date_format:H:i'],
            'quiet_hours_end' => ['required', 'date_format:H:i'],
            'quiet_hours_timezone' => ['required', 'string', 'timezone'],
        ];
    }
}
