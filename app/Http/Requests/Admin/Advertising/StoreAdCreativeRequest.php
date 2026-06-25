<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Advertising;

use App\Enums\AdCreativeType;
use App\Http\Requests\BaseFormRequest;
use App\Support\Advertising\AdUrlSafety;
use Closure;
use Illuminate\Validation\Rule;

/**
 * إنشاء إبداع. النوع video مرفوض كـ«ميزة غير مُفعّلة في هذه المرحلة» (لا كمفهوم غير صالح):
 * يبقى قيمة تعداد معروفة ويُردّ برسالة قُدرة صريحة. image ⇒ يتطلّب media_asset_id؛
 * html ⇒ يتطلّب html_code (يُنقّى في الـ Action). landing_url يُتحقَّق (http/https فقط).
 */
class StoreAdCreativeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ad_campaign_id' => ['required', 'integer', 'exists:ad_campaigns,id'],
            'type' => [
                'required',
                Rule::in(AdCreativeType::values()),
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === AdCreativeType::Video->value) {
                        $fail(__('ads.creative.video_not_enabled'));
                    }
                },
            ],
            'title' => ['required', 'string', 'max:200'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'landing_url' => [
                'sometimes', 'nullable', 'string', 'max:500',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && ! AdUrlSafety::isSafe((string) $value)) {
                        $fail(__('ads.creative.invalid_landing_url'));
                    }
                },
            ],
            'html_code' => ['required_if:type,html', 'nullable', 'string', 'max:65535'],
            'media_asset_id' => ['required_if:type,image', 'nullable', 'integer', 'exists:media_assets,id'],
            'weight' => ['sometimes', 'integer', 'min:1', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
