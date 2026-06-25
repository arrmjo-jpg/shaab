<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Page;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DeletePageAction
{
    public function handle(Page $page): JsonResponse
    {
        $page->delete(); // soft delete — يختفي من العام → إبطال كاش/حافة CDN

        Cache::tags(PageCacheTags::invalidationTags($page))->flush();
        PageCdnPurge::purge($page);

        return ApiResponse::success(__('page.deleted'));
    }
}
