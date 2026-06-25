<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\PageStatus;
use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Models\User;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Content\PageContentSanitizer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء صفحة ثابتة (مسودّة) — الانتقالات لاحقاً عبر TransitionPageStatusAction.
 * المحتوى يُنقّى بقائمة بيضاء (PageContentSanitizer) على مسار الكتابة.
 */
class CreatePageAction
{
    public function handle(array $validated, User $actor): JsonResponse
    {
        $page = DB::transaction(function () use ($validated, $actor): Page {
            $page = new Page;
            $page->fill([
                'author_id' => $validated['author_id'] ?? $actor->id,
                'status' => PageStatus::Draft->value,
                'locale' => $validated['locale'],
                'title' => $validated['title'],
                'content' => PageContentSanitizer::sanitize($validated['content'] ?? null),
                'seo_title' => $validated['seo_title'] ?? null,
                'seo_description' => $validated['seo_description'] ?? null,
                'seo_keywords' => $validated['seo_keywords'] ?? null,
                'canonical_url' => $validated['canonical_url'] ?? null,
                'robots' => $validated['robots'] ?? null,
                'template' => $validated['template'] ?? null,
                'show_in_header' => $validated['show_in_header'] ?? false,
                'show_in_footer' => $validated['show_in_footer'] ?? false,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            if (! empty($validated['slug'])) {
                $page->slug = $validated['slug'];
            }

            $page->save();

            return $page;
        });

        Cache::tags(PageCacheTags::invalidationTags($page))->flush();
        PageCdnPurge::purge($page);

        return ApiResponse::success(
            __('page.created'),
            new PageResource($page->load('author:id,name')),
            201
        );
    }
}
