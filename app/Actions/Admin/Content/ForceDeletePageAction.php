<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Page;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ForceDeletePageAction
{
    public function handle(Page $page): JsonResponse
    {
        // التقاط الوسوم/الروابط قبل الحذف (نحتاج locale/slug للإبطال).
        $tags = PageCacheTags::invalidationTags($page);
        PageCdnPurge::purge($page);

        $page->forceDelete();

        Cache::tags($tags)->flush();

        return ApiResponse::success(__('page.force_deleted'));
    }
}
