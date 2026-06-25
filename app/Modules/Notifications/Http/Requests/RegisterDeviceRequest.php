<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** تسجيل/تحديث جهاز push (ضيف أو مُصادَق). device_id = UUID لكلّ تثبيت. */
class RegisterDeviceRequest extends FormRequest
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
            'platform' => ['required', 'string', Rule::in(DevicePlatform::values())],
            'locale' => ['nullable', 'string', 'max:5'],
        ];
    }
}
