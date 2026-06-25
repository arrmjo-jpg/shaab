<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Whatsapp;

use App\Enums\WhatsappCampaignType;
use App\Enums\WhatsappMediaType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * إنشاء حملة واتساب — نوعان فقط:
 *   • promo: نص و/أو وسيط (صورة/فيديو) — الأنواع الخمسة المتفق عليها.
 *   • article: مقال فقط (المحتوى يُجلب تلقائياً: عنوان/صورة/ملخص/رابط).
 * المستلمون = مجموعات مختارة. جدولة اختيارية (scheduled_at مستقبلاً) أو إرسال لاحق يدويّ.
 */
class StoreWhatsappCampaignRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // التفويض عبر permission middleware على المسار.
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'type' => ['required', Rule::in(WhatsappCampaignType::values())],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*' => ['integer', Rule::exists('whatsapp_groups', 'id')->whereNull('deleted_at')],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],

            // promo فقط:
            'message_text' => ['nullable', 'string', 'max:4096'],
            'media_type' => ['required_if:type,promo', Rule::in(WhatsappMediaType::values())],
            'media_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],

            // article فقط:
            'article_id' => ['required_if:type,article', 'nullable', 'integer', Rule::exists('articles', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->input('type') !== WhatsappCampaignType::Promo->value) {
                return;
            }

            $mediaType = $this->input('media_type', WhatsappMediaType::None->value);
            $hasText = trim((string) $this->input('message_text', '')) !== '';
            $hasMedia = $mediaType !== WhatsappMediaType::None->value;

            // إعلانية بلا نص وبلا وسيط = لا شيء لإرساله.
            if (! $hasText && ! $hasMedia) {
                $v->errors()->add('message_text', __('whatsapp.campaign.empty_promo'));
            }
            // وسيط مختار بلا أصل.
            if ($hasMedia && $this->input('media_asset_id') === null) {
                $v->errors()->add('media_asset_id', __('whatsapp.campaign.media_required'));
            }
        });
    }
}
