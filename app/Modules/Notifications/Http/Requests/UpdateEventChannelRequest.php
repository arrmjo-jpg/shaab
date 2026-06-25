<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeliveryMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تحرير صفّ مصفوفة (event × channel). البوّابة على المسار (permission:notifications.manage).
 * لا يغيّر القناة/الحدث (مفتاحان ثابتان) — فقط السلوك (mode/priority/fallback/template/audience).
 */
final class UpdateEventChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(array_column(DeliveryMode::cases(), 'value'))],
            'channel_priority' => ['required', 'integer', 'min:1', 'max:100'],
            'fallback_channel' => ['nullable', Rule::in(array_column(ChannelKey::cases(), 'value'))],
            'template_id' => ['nullable', 'integer', 'exists:notification_templates,id'],
            'default_audience_id' => ['nullable', 'integer'],
        ];
    }
}
