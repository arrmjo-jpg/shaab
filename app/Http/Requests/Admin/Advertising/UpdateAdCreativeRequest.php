<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdCreativeType;
use App\Http\Requests\BaseFormRequest;
use App\Support\Advertising\AdUrlSafety;
use Closure;
use Illuminate\Validation\Rule;

/**
 * تعديل إبداع. الحملة (ad_campaign_id) غير قابلة للتغيير (تُحذف من القواعد ⇒ تُتجاهل).
 * النوع video يبقى مرفوضاً كميزة غير مُفعّلة. required_if للنوع المُرسَل فقط (تحديث جزئيّ).
 */
class UpdateAdCreativeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => [
                'sometimes',
                Rule::in(AdCreativeType::values()),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === AdCreativeType::Video->value) {
                        $fail(__('ads.creative.video_not_enabled'));
                    }
                },
            ],
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'landing_url' => [
                'sometimes', 'nullable', 'string', 'max:500',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && ! AdUrlSafety::isSafe((string) $value)) {
                        $fail(__('ads.creative.invalid_landing_url'));
                    }
                },
            ],
            'html_code' => ['sometimes', 'required_if:type,html', 'nullable', 'string', 'max:65535'],
            'media_asset_id' => ['sometimes', 'required_if:type,image', 'nullable', 'integer', 'exists:media_assets,id'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
