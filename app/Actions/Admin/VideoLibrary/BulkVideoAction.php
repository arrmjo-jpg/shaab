<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPlaylist;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * عمليات جماعية على فيديوهات المكتبة بنجاح جزئي (partial success): تُعالَج العناصر
 * الصالحة وتُتخطّى المخالِفة مع ذكر السبب — لا تفشل الدفعة كلها إلا لسبب كارثي
 * (نقص صلاحية = 403). تفرض ثوابت النطاق (النشر يحترم hasPublishableMedia)، آمنة
 * من التكرار (تخطّي نفس-الحالة/المُضاف مسبقاً)، وتُبطِل الكاش بدقّة دفعةً واحدة.
 *
 * صلاحيات لكل عملية (إضافةً إلى البوابة الخشنة videos.edit على المسار):
 *   publish → videos.publish · delete → videos.delete · add_to_playlist →
 *   video-playlists.manage · (feature/unpublish/move_category ضمن videos.edit).
 */
final class BulkVideoAction
{
    private const ABILITY = [
        'publish' => 'videos.publish',
        'delete' => 'videos.delete',
        'add_to_playlist' => 'video-playlists.manage',
    ];

    public function handle(array $validated, User $actor): JsonResponse
    {
        $action = $validated['action'];

        // صلاحية العملية (كارثي — يفشل كامل الطلب 403 لا نجاح جزئي).
        $required = self::ABILITY[$action] ?? null;
        if ($required !== null && ! $actor->can($required)) {
            return ApiResponse::error(__('video.bulk_forbidden'), [], 403);
        }

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $videos = Video::query()->whereIn('id', $ids)->with(['mediaAsset', 'category'])->get()->keyBy('id');

        $processed = 0;
        $skipped = [];
        $tags = [];

        // قائمة التشغيل (مرّة واحدة) لعملية الإضافة.
        $playlist = $action === 'add_to_playlist'
            ? VideoPlaylist::find($validated['playlist_id'])
            : null;
        $playlistExisting = $playlist?->videos()->pluck('videos.id')->flip();
        $playlistPosition = $playlist
            ? (int) DB::table('playlist_video')->where('video_playlist_id', $playlist->id)->max('position')
            : 0;

        DB::transaction(function () use (
            $action, $ids, $videos, $validated, $actor, $playlist, &$playlistExisting,
            &$playlistPosition, &$processed, &$skipped, &$tags
        ): void {
            foreach ($ids as $id) {
                $video = $videos->get($id);
                if ($video === null) {
                    $skipped[] = ['id' => $id, 'reason' => 'not_found'];

                    continue;
                }

                $result = match ($action) {
                    'publish' => $this->publish($video, $actor),
                    'unpublish' => $this->setStatus($video, VideoStatus::Draft),
                    'feature' => $this->feature($video, (bool) $validated['value']),
                    'move_category' => $this->moveCategory($video, $validated['video_category_id'] ?? null),
                    'add_to_playlist' => $this->addToPlaylist($video, $playlist, $playlistExisting, $playlistPosition),
                    'delete' => $this->delete($video),
                    default => 'skip:unknown',
                };

                if ($result === 'ok') {
                    $processed++;
                    $tags = array_merge($tags, VideoCacheTags::invalidationTags($video, categorySlug: $video->category?->slug));
                } else {
                    $skipped[] = ['id' => $id, 'reason' => substr($result, 5)]; // "skip:<reason>"
                }
            }
        });

        if ($playlist !== null) {
            $tags = array_merge($tags, VideoCacheTags::playlistInvalidationTags($playlist));
        }
        if ($tags !== []) {
            $tags = array_values(array_unique($tags));
            Cache::tags($tags)->flush();
            FrontendRevalidate::tags(FrontendCacheTags::fromVideoTags($tags));
        }

        return ApiResponse::success(
            __('video.bulk_done', ['processed' => $processed, 'requested' => count($ids)]),
            [
                'action' => $action,
                'requested' => count($ids),
                'processed' => $processed,
                'skipped' => $skipped,
            ]
        );
    }

    /** النشر يحترم حارس الجاهزية + يتخطّى المنشور مسبقاً. */
    private function publish(Video $video, User $actor): string
    {
        if ($video->status === VideoStatus::Published) {
            return 'skip:already_in_state';
        }
        if (! $video->hasPublishableMedia()) {
            return 'skip:media_not_ready';
        }

        $video->status = VideoStatus::Published->value;
        $video->published_at = $video->published_at ?? now();
        $video->published_by_id = $actor->id;
        $video->save();

        return 'ok';
    }

    private function setStatus(Video $video, VideoStatus $status): string
    {
        if ($video->status === $status) {
            return 'skip:already_in_state';
        }
        $video->status = $status->value;
        $video->save();

        return 'ok';
    }

    private function feature(Video $video, bool $value): string
    {
        if ($video->is_featured === $value) {
            return 'skip:already_in_state';
        }
        $video->is_featured = $value;
        $video->save();

        return 'ok';
    }

    private function moveCategory(Video $video, ?int $categoryId): string
    {
        if ($video->video_category_id === $categoryId) {
            return 'skip:already_in_state';
        }
        $video->video_category_id = $categoryId;
        $video->save();
        // تحميل التصنيف الجديد لإبطال وسمه أيضاً.
        $video->setRelation('category', $video->category()->first());

        return 'ok';
    }

    /**
     * إضافة آمنة من التكرار إلى قائمة تشغيل (تُلحَق بترتيب صريح).
     *
     * @param  Collection<int,int>|null  $existing
     */
    private function addToPlaylist(Video $video, ?VideoPlaylist $playlist, $existing, int &$position): string
    {
        if ($playlist === null) {
            return 'skip:playlist_missing';
        }
        if ($existing->has($video->id)) {
            return 'skip:already_in_playlist';
        }
        $playlist->videos()->attach($video->id, ['position' => ++$position]);
        $existing->put($video->id, true);

        return 'ok';
    }

    private function delete(Video $video): string
    {
        $video->delete();

        return 'ok';
    }
}
