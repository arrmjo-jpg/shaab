<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Responses\ApiResponse;
use App\Support\Video\VideoCategoryHierarchyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CreateVideoCategoryAction
{
    public function handle(array $validated): JsonResponse
    {
        $parentId = $validated['parent_id'] ?? null;
        $locale = $validated['locale'];

        if ($denied = VideoCategoryHierarchyGuard::check(null, $parentId, $locale)) {
            return $denied;
        }

        $category = new VideoCategory;
        $category->fill([
            'parent_id' => $parentId,
            'locale' => $locale,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'cover_media_id' => $validated['cover_media_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'seo_title' => $validated['seo_title'] ?? null,
            'seo_description' => $validated['seo_description'] ?? null,
        ]);

        if (! empty($validated['slug'])) {
            $category->slug = $validated['slug'];
        }

        $category->save();

        Cache::tags([VideoCacheTags::ALL])->flush();

        return ApiResponse::success(
            __('video_category.created'),
            new VideoCategoryResource($category),
            201
        );
    }
}
