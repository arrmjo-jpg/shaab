<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ReelStatus;
use App\Http\Resources\Admin\Content\ReelResource;
use App\Models\Reel;
use App\Models\User;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelAuthorizationGuard;
use App\Support\Content\ReelCdnPurge;
use App\Support\Content\ReelRevisionRecorder;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء ريل (مسودّة) — الانتقالات لاحقاً عبر TransitionReelStatusAction.
 */
class CreateReelAction
{
    public function handle(array $validated, User $actor): JsonResponse
    {
        if ($denied = ReelAuthorizationGuard::forCreate($actor, $validated['author_id'] ?? null)) {
            return $denied;
        }

        $authorId = ReelAuthorizationGuard::resolveAuthorId($actor, $validated['author_id'] ?? null);

        $reel = DB::transaction(function () use ($validated, $actor, $authorId): Reel {
            $reel = new Reel;
            $reel->fill([
                'author_id' => $authorId,
                'media_asset_id' => $validated['media_asset_id'] ?? null,
                'status' => ReelStatus::Draft->value,
                'is_featured' => $validated['is_featured'] ?? false,
                'locale' => $validated['locale'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots' => $validated['robots'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['slug'])) {
                $reel->slug = $validated['slug'];
            }

            $reel->save();

            ReelRevisionRecorder::snapshot($reel, $actor->id);

            return $reel;
        });

        Cache::tags(ReelCacheTags::invalidationTags($reel))->flush();
        ReelCdnPurge::purge($reel);

        return ApiResponse::success(
            __('reel.created'),
            new ReelResource($reel->load(['author:id,name', 'mediaAsset'])),
            201
        );
    }
}
