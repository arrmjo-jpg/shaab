<?php

declare(strict_types=1);

namespace App\Modules\CDN\Actions;

use App\Modules\CDN\Services\CloudflareClient;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class PurgeAllAction
{
    public function handle(): JsonResponse
    {
        $client = new CloudflareClient;

        if (! $client->enabled()) {
            return ApiResponse::error(__('cdn.disabled'), [], 422);
        }

        if ($client->purgeAll()) {
            return ApiResponse::success(__('cdn.purge_all_done'));
        }

        return ApiResponse::error(__('cdn.purge_failed'), [], 422);
    }
}
