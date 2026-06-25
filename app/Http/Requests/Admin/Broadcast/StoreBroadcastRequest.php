<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Broadcast;

use App\Enums\BroadcastKind;
use App\Enums\BroadcastSourceType;
use App\Http\Requests\BaseFormRequest;
use App\Rules\ResolvableBroadcastSourceUrl;
use Illuminate\Validation\Rule;

/**
 * إنشاء بثّ. المصدر مطلوب (نوع + رابط موثوق). الحالة لا تُضبط هنا (تبدأ مسودّة دائماً
 * — الانتقالات في B2). source_url يُتحقَّق عبر ResolvableBroadcastSourceUrl
 * (https + قائمة موثوقة للنوع).
 */
class StoreBroadcastRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'kind' => ['required', Rule::in(BroadcastKind::values())],
            'source_type' => ['required', Rule::in(BroadcastSourceType::values())],
            'source_url' => ['required', 'string', 'max:2048', new ResolvableBroadcastSourceUrl],

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
                Rule::unique('broadcasts', 'slug'),
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
