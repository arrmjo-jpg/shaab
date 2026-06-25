<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Http\Requests\BaseFormRequest;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Rules\OwnedMediaAsset;
use Closure;
use Illuminate\Validation\Rule;

/**
 * إرسال ريل من الكاتب (نطاق عام).
 *
 * مثل StoreReelRequest الإداريّ: فيديو الريل (media_asset_id) **اختياريّ عند الإنشاء**
 * (الجاهزيّة تُفرَض عند النشر) — لكنّه هنا يخضع لحارس ملكيّة الكاتب (OwnedMediaAsset)
 * سدّاً لـ IDOR، ويجب أن يكون فيديو مرفوعاً. الإسناد داخل CreateReelAction (لا منطق جديد).
 * النموذج يطلب الفيديو client-side. مُستبعَد: author_id (إسناد ذاتيّ) ·
 * is_featured/sort_order (تحريريّ) · status (الإنشاء = Draft).
 */
class PublicStoreReelRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'locale' => ['required', 'string', Rule::in(Reel::LOCALES)],

            // فيديو الريل المرفوع — يملكه الكاتب (OwnedMediaAsset) ويكون فيديو مرفوعاً.
            'media_asset_id' => [
                'sometimes', 'nullable', 'integer',
                new OwnedMediaAsset($this->user()?->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $asset = MediaAsset::find($value);
                    if ($asset === null || ! $asset->isUploadedVideo()) {
                        $fail(__('video.source.not_uploaded_video'));
                    }
                },
            ],

            // slug يدوي اختياري — أحرف يونيكود (تشمل العربية)، فرادة لكل لغة.
            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('reels', 'slug')->where(
                    fn ($q) => $q->where('locale', $this->input('locale'))
                ),
            ],

            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
