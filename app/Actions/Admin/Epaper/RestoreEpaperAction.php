<?php

declare(strict_types=1);

namespace App\Actions\Admin\Epaper;

use App\Http\Resources\Admin\Epaper\EpaperResource;
use App\Models\Epaper;
use App\Support\Epaper\EpaperSearchIndexer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** استرجاع عدد محذوف منطقياً. */
class RestoreEpaperAction
{
    public function handle(Epaper $epaper): JsonResponse
    {
        $epaper->restore();

        // مُسترجَع ⇒ أعِد فهرسة صفحاته إن عاد منشوراً.
        EpaperSearchIndexer::queueSync($epaper->id);

        return ApiResponse::success(
            __('epaper.restored'),
            new EpaperResource($epaper->fresh()->load(['mediaAsset', 'author'])),
        );
    }
}
