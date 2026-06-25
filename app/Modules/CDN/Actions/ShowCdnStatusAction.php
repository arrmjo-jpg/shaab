<?php

declare(strict_types=1);

namespace App\Modules\CDN\Actions;

use App\Modules\CDN\Resources\CdnStatusResource;
use App\Modules\CDN\Support\CdnStats;
use App\Settings\CdnSettings;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ShowCdnStatusAction
{
    public function handle(): JsonResponse
    {
        $payload = Cache::tags(['cdn'])->remember(
            CacheKeys::make('cdn', 'status'),
            CacheTtl::SHORT,
            function (): array {
                $s = app(CdnSettings::class);

                return [
                    'enabled' => $s->cdn_enabled,
                    'configured' => $s->cdn_api_token !== '' && $s->cdn_zone_id !== '',
                    'plan' => $s->cdn_plan,
                    'auto_purge' => $s->cdn_auto_purge,
                    'stats' => (new CdnStats)->summary(),
                ];
            }
        );

        return ApiResponse::success(data: new CdnStatusResource($payload));
    }
}
