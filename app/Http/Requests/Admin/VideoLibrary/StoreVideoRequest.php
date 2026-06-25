<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Enums\VideoVisibility;
use App\Http\Requests\BaseFormRequest;
use App\Models\MediaAsset;
use App\Models\Video;
use App\Rules\ResolvableVideoSourceUrl;
use Illuminate\Validation\Rule;

/**
 * إنشاء فيديو. متطلّب صريح: **مصدر وسائط مطلوب حتى للمسودّة** (أصل مرفوع — ولو
 * كان قيد المعالجة — أو رابط خارجي صالح). لا يُشترَط أن يكون المصدر «جاهزاً
 * للتشغيل»؛ هذا القيد يُفرَض فقط عند النشر/الجدولة (TransitionVideoStatusAction).
 */
class StoreVideoRequest extends BaseFormRequest
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
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'video_category_id' => ['sometimes', 'nullable', 'integer', 'exists:video_categories,id'],

            // ── المصدر مطلوب (أحدهما) حتى للمسودّة — الجاهزية ليست شرطاً هنا ──
            'media_asset_id' => [
                'required_without:source_url', 'nullable', 'integer', 'exists:media_assets,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $asset = MediaAsset::find($value);
                    // أصل مرفوع (فيديو) — يُقبل ولو كان قيد المعالجة (غير جاهز بعد).
                    if ($asset === null || ! $asset->isUploadedVideo()) {
                        $fail(__('video.source.not_uploaded_video'));
                    }
                },
            ],
            'source_url' => ['required_without:media_asset_id', 'nullable', 'string', 'max:2048', new ResolvableVideoSourceUrl],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('videos', 'slug')->where(fn ($q) => $q->where('locale', $this->input('locale'))),
            ],

            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', Rule::in(VideoVisibility::values())],
            'is_featured' => ['sometimes', 'boolean'],

            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
