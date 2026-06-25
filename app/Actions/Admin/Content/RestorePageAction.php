<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RestorePageAction
{
    public function handle(Page $page): JsonResponse
    {
        if (! $page->trashed()) {
            return ApiResponse::error(__('page.not_trashed'), [], 422);
        }

        $page->restore();

        Cache::tags(PageCacheTags::invalidationTags($page))->flush();
        PageCdnPurge::purge($page);

        return ApiResponse::success(
            __('page.restored'),
            new PageResource($page->fresh())
        );
    }
}
