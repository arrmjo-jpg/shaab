<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use App\Http\Resources\Admin\VideoLibrary\VideoResource;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Video;
use App\Support\Cache\VideoCacheTags;
use App\Support\Content\VideoAuthorizationGuard;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use App\Support\Video\VideoMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء فيديو (مسودّة) — يُربط المصدر دائماً (مطلوب حتى للمسودّة)، لكن دون فرض
 * جاهزية التشغيل (تُفرَض عند النشر/الجدولة فقط). الانتقالات لاحقاً عبر
 * TransitionVideoStatusAction.
 */
class CreateVideoAction
{
    public function handle(array $validated, User $actor): JsonResponse
    {
        if ($denied = VideoAuthorizationGuard::forCreate($actor, $validated['author_id'] ?? null)) {
            return $denied;
        }

        $authorId = VideoAuthorizationGuard::resolveAuthorId($actor, $validated['author_id'] ?? null);

        $video = DB::transaction(function () use ($validated, $actor, $authorId): Video {
            $video = new Video;
            $video->fill([
                'author_id' => $authorId,
                'video_category_id' => $validated['video_category_id'] ?? null,
                'status' => VideoStatus::Draft->value,
                'visibility' => $validated['visibility'] ?? VideoVisibility::Public->value,
                'is_featured' => $validated['is_featured'] ?? false,
                'locale' => $validated['locale'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'excerpt' => $validated['excerpt'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots' => $validated['robots'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['slug'])) {
                $video->slug = $validated['slug'];
            }

            $video->save();

            // ربط المصدر مشروطاً بوجوده: رابط خارجي، أو أصل مرفوع، أو لا شيء
            // (مسودّة بلا مصدر — يُربط لاحقاً؛ hasPublishableMedia يمنع نشرها).
            if (! empty($validated['source_url'])) {
                VideoMedia::attachExternalSource($video, $validated['source_url'], $actor);
            } elseif (! empty($validated['media_asset_id'])) {
                VideoMedia::attachUploadedAsset($video, MediaAsset::findOrFail($validated['media_asset_id']));
            }

            if (array_key_exists('tags', $validated)) {
                $video->syncTagsWithType($validated['tags'] ?? [], 'video');
            }

            return $video;
        });

        $video->refresh()->load(['author:id,name', 'mediaAsset', 'category']);
        $tags = VideoCacheTags::invalidationTags($video, categorySlug: $video->category?->slug);
        Cache::tags($tags)->flush();
        FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));

        return ApiResponse::success(
            __('video.created'),
            new VideoResource($video),
            201
        );
    }
}
