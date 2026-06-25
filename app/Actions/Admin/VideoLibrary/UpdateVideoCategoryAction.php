<?php

declare(strict_types=1);

namespace App\Actions\Admin\VideoLibrary;

use App\Http\Resources\Admin\VideoLibrary\VideoCategoryResource;
use App\Models\VideoCategory;
use App\Support\Cache\VideoCacheTags;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use App\Support\Video\VideoCategoryHierarchyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateVideoCategoryAction
{
    public function handle(VideoCategory $category, array $validated): JsonResponse
    {
        $locale = $validated['locale'] ?? $category->locale;
        $parentId = array_key_exists('parent_id', $validated) ? $validated['parent_id'] : $category->parent_id;

        if ($denied = VideoCategoryHierarchyGuard::check($category, $parentId, $locale)) {
            return $denied;
        }

        foreach (['name', 'locale', 'description', 'cover_media_id', 'sort_order', 'seo_title', 'seo_description'] as $field) {
            if (array_key_exists($field, $validated)) {
                $category->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('parent_id', $validated)) {
            $category->parent_id = $validated['parent_id'];
        }

        if (array_key_exists('is_active', $validated)) {
            $category->is_active = (bool) $validated['is_active'];
        }

        if (array_key_exists('slug', $validated) && ! empty($validated['slug'])) {
            $category->slug = $validated['slug'];
        }

        $category->save();

        Cache::tags([VideoCacheTags::ALL])->flush();
        FrontendRevalidate::tags(FrontendCacheTags::videoCategory($category));

        return ApiResponse::success(
            __('video_category.updated'),
            new VideoCategoryResource($category->fresh())
        );
    }
}
