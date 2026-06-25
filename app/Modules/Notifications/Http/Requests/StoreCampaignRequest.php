<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Support\EventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تأليف حملة يدويّة. event_key يجب أن يكون من أحداث الكتالوج القابلة للإرسال اليدويّ. channels
 * اختياريّة (غيابها ⇒ تُستعمل المصفوفة). البوّابة على المسار (permission:notifications.send).
 */
final class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'event_key' => ['required', 'string', Rule::in(EventCatalog::manualDispatchable())],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:1000'],
            'deep_link_type' => ['nullable', 'string', 'max:30'],
            'deep_link_value' => ['nullable', 'string', 'max:500'],
            'audience' => ['required', 'string', Rule::in(array_column(AudienceType::cases(), 'value'))],
            'audience_params' => ['nullable', 'array'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(array_column(ChannelKey::cases(), 'value'))],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'requires_approval' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', 'max:10'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'variables' => ['nullable', 'array'],
        ];
    }
}
