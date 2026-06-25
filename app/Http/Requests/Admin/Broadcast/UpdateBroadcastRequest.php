<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

use App\Enums\BroadcastKind;
use App\Enums\BroadcastSourceType;
use App\Http\Requests\BaseFormRequest;
use App\Models\Broadcast;
use App\Rules\ResolvableBroadcastSourceUrl;
use Illuminate\Validation\Rule;

/**
 * تعديل بثّ. كل الحقول 'sometimes'. الحالة لا تُعدَّل هنا (الانتقالات منفصلة — B2)؛
 * تُرفَض إن أُرسلت. عند إرسال رابط مصدر جديد يلزم إرسال نوعه معه (required_with)
 * كي يتحقّق ResolvableBroadcastSourceUrl من القائمة الموثوقة الصحيحة.
 */
class UpdateBroadcastRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Broadcast $broadcast */
        $broadcast = $this->route('broadcast');

        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'kind' => ['sometimes', Rule::in(BroadcastKind::values())],

            'source_type' => ['sometimes', 'required_with:source_url', Rule::in(BroadcastSourceType::values())],
            'source_url' => ['sometimes', 'string', 'max:2048', new ResolvableBroadcastSourceUrl],

            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:broadcast_categories,id'],
            'vod_video_id' => ['sometimes', 'nullable', 'integer', 'exists:videos,id'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],

            'seo_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'seo_keywords' => ['sometimes', 'nullable', 'string', 'max:255'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:255'],
            'robots' => ['sometimes', 'nullable', 'string', 'max:50'],

            'slug' => [
                'sometimes', 'nullable', 'string', 'max:190',
                'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u',
                Rule::unique('broadcasts', 'slug')->ignore($broadcast->id),
            ],

            'thumbnail_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'poster_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'cover_media_id' => ['sometimes', 'nullable', 'integer', 'exists:media_assets,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],

            'is_featured' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
