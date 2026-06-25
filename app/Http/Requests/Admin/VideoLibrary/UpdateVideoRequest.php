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
 * تعديل فيديو. المصدر اختياري عند التعديل (تعديل البيانات الوصفية لا يستلزم
 * إعادة إرسال المصدر)، لكن إن أُرسل مصدر جديد يُتحقَّق منه. الجاهزية لا تُفرَض هنا.
 */
class UpdateVideoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Video $video */
        $video = $this->route('video');
        $locale = $this->input('locale', $video->locale);

        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'locale' => ['sometimes', 'string', Rule::in(Video::LOCALES)],
            'author_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'video_category_id' => ['sometimes', 'nullable', 'integer', 'exists:video_categories,id'],

            // مصدر اختياري عند التعديل — إن وُجد يُتحقَّق (مرفوع فيديو أو رابط صالح).
            'media_asset_id' => [
                'sometimes', 'nullable', 'integer', 'exists:media_assets,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $asset = MediaAsset::find($value);
                    if ($asset === null || ! $asset->isUploadedVideo()) {
                        $fail(__('video.source.not_uploaded_video'));
                    }
                },
            ],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:2048', new ResolvableVideoSourceUrl],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('videos', 'slug')->where(fn ($q) => $q->where('locale', $locale))->ignore($video->id),
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
