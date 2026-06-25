<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use App\Http\Requests\BaseFormRequest;
use App\Models\MediaAsset;
use App\Models\Video;
use App\Rules\OwnedMediaAsset;
use App\Rules\ResolvableVideoSourceUrl;
use Closure;
use Illuminate\Validation\Rule;

/**
 * إرسال فيديو من الكاتب (نطاق عام).
 *
 * نسخة من StoreVideoRequest الإداري: حقول المحتوى + **مصدر الفيديو مثل الإدارة
 * تماماً (رفع أو رابط خارجيّ)** — لكن المرفوع يخضع لحارس ملكيّة الكاتب (OwnedMediaAsset)
 * سدّاً لـ IDOR. الإسناد الفعليّ للمصدر داخل CreateVideoAction عبر VideoMedia (لا منطق جديد).
 * مُستبعَد عمداً (يُفرَض في الـ Actions/Guards): author_id (إسناد ذاتيّ) ·
 * is_featured/sort_order/visibility (قرار تحريريّ) · status (الإنشاء = Draft).
 */
class PublicStoreVideoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'locale' => ['required', 'string', Rule::in(Video::LOCALES)],
            'video_category_id' => ['sometimes', 'nullable', 'integer', 'exists:video_categories,id'],

            // ── المصدر مطلوب (أحدهما) — نفس قاعدة الإدارة. ──
            // رفع: أصل فيديو مرفوع يملكه الكاتب (OwnedMediaAsset + isUploadedVideo).
            'media_asset_id' => [
                'required_without:source_url', 'nullable', 'integer',
                new OwnedMediaAsset($this->user()?->id),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $asset = MediaAsset::find($value);
                    // يُقبل ولو كان قيد المعالجة (الجاهزية تُفرَض عند النشر لا الإرسال).
                    if ($asset === null || ! $asset->isUploadedVideo()) {
                        $fail(__('video.source.not_uploaded_video'));
                    }
                },
            ],
            // رابط: مصدر خارجيّ مدعوم (youtube|vimeo|direct_mp4) — نفس قاعدة الإدارة.
            'source_url' => ['required_without:media_asset_id', 'nullable', 'string', 'max:2048', new ResolvableVideoSourceUrl],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('videos', 'slug')->where(fn ($q) => $q->where('locale', $this->input('locale'))),
            ],

            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
