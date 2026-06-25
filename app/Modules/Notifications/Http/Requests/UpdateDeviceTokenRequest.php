<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** تحديث FCM token لجهاز قائم (تدوير) — لا يمسّ user_id. */
class UpdateDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:100'],
            'fcm_token' => ['required', 'string', 'max:255'],
        ];
    }
}
