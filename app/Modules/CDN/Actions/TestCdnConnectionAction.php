<?php

declare(strict_types=1);

namespace App\Modules\CDN\Actions;

use App\Modules\CDN\Services\CloudflareClient;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TestCdnConnectionAction
{
    public function handle(): JsonResponse
    {
        $client = new CloudflareClient;

        if ($client->settings()->cdn_api_token === '') {
            return ApiResponse::error(__('cdn.token_missing'), [], 422);
        }

        $result = $client->verifyToken();

        Cache::tags(['cdn'])->flush();

        if (($result['ok'] ?? false) === true) {
            return ApiResponse::success(__('cdn.test_success'));
        }

        return ApiResponse::error(__('cdn.test_failed'), [], 422);
    }
}
