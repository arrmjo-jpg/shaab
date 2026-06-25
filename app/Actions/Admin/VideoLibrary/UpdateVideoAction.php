<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoUrlHistory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use App\Support\Video\VideoMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تعديل فيديو. لا يُغيّر الحالة (الانتقالات منفصلة). إن تغيّر slug/locale يُسجَّل
 * المسار القانوني القديم في video_url_history لإعادة التوجيه 301 (المُحلِّل بالمرحلة 5).
 * المصدر اختياري؛ إن أُرسل يُعاد ربطه دون فرض جاهزية.
 */
class UpdateVideoAction
{
    public function handle(Video $video, array $validated, User $actor): JsonResponse
    {
        $oldLocale = $video->locale;
        $oldSlug = (string) $video->slug;
        $oldPath = $video->canonicalPath();
        $oldCategorySlug = $video->category?->slug;

        $video = DB::transaction(function () use ($video, $validated, $actor, $oldLocale, $oldSlug, $oldPath): Video {
            foreach (['title', 'locale', 'description', 'excerpt', 'visibility', 'video_category_id', 'author_id',
                'seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots', 'sort_order'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $video->{$field} = $validated[$field];
                }
            }

            if (array_key_exists('is_featured', $validated)) {
                $video->is_featured = (bool) $validated['is_featured'];
            }

            if (array_key_exists('slug', $validated) && ! empty($validated['slug'])) {
                $video->slug = $validated['slug'];
            }

            // إعادة ربط المصدر إن أُرسل (دون فرض جاهزية).
            if (! empty($validated['source_url'])) {
                VideoMedia::attachExternalSource($video, $validated['source_url'], $actor);
            } elseif (! empty($validated['media_asset_id'])) {
                VideoMedia::attachUploadedAsset($video, MediaAsset::findOrFail($validated['media_asset_id']));
            }

            $video->save();

            // 301: التقاط المسار القديم عند تغيّر slug/locale (يشمل المحذوف عبر unique).
            if ($video->locale !== $oldLocale || (string) $video->slug !== $oldSlug) {
                VideoUrlHistory::firstOrCreate(
                    ['locale' => $oldLocale, 'old_path' => $oldPath],
                    ['video_id' => $video->id, 'reason' => 'slug_or_locale_change'],
                );
            }

            if (array_key_exists('tags', $validated)) {
                $video->syncTagsWithType($validated['tags'] ?? [], 'video');
            }

            return $video;
        });

        $video->refresh()->load(['author:id,name', 'mediaAsset', 'category']);

        // إبطال حبيبي: الجديد + القديم (لغة/slug/تصنيف).
        $tags = VideoCacheTags::invalidationTags(
            $video,
            oldLocale: $oldLocale,
            oldSlug: $oldSlug,
            categorySlug: $video->category?->slug,
            oldCategorySlug: $oldCategorySlug,
        );
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video.updated'),
            new VideoResource($video)
        );
    }
}
