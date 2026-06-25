<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\ReelResource;
use App\Models\Reel;
use App\Models\ReelUrlHistory;
use App\Models\User;
use App\Support\Cache\ReelCacheTags;
use App\Support\Content\ReelCdnPurge;
use App\Support\Content\ReelRevisionRecorder;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateReelAction
{
    /** الحقول القابلة للتعديل المباشر (الحالة عبر مسار الانتقال فقط). */
    private const FIELDS = [
        'author_id', 'media_asset_id', 'locale', 'title',
        'description', 'is_featured', 'seo_title', 'seo_description', 'seo_keywords',
        'canonical_url', 'robots', 'sort_order',
    ];

    public function handle(Reel $reel, array $validated, User $actor): JsonResponse
    {
        // التُقط قبل التعديل لإبطال كاش/حافة الـ slug/اللغة القديمة عند تغيّرهما.
        $oldLocale = $reel->locale;
        $oldSlug = (string) $reel->slug;
        $oldPath = $reel->canonicalPath();

        $reel = DB::transaction(function () use ($reel, $validated, $actor, $oldLocale, $oldPath): Reel {
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    $reel->{$field} = $validated[$field];
                }
            }

            if (! empty($validated['slug'])) {
                $reel->slug = $validated['slug'];
            }

            $reel->save();

            // التقط المسار القانوني القديم عند تغيّره (لإعادة توجيه 301).
            $newPath = $reel->fresh()->canonicalPath();
            if ($newPath !== $oldPath) {
                ReelUrlHistory::firstOrCreate(
                    ['locale' => $oldLocale, 'old_path' => $oldPath],
                    ['reel_id' => $reel->id, 'reason' => 'canonical_change'],
                );
            }

            ReelRevisionRecorder::snapshot($reel, $actor->id);

            return $reel;
        });

        Cache::tags(ReelCacheTags::invalidationTags($reel, $oldLocale, $oldSlug))->flush();
        ReelCdnPurge::purge($reel, $oldPath);

        return ApiResponse::success(
            __('reel.updated'),
            new ReelResource($reel->fresh()->load(['author:id,name', 'mediaAsset']))
        );
    }
}
