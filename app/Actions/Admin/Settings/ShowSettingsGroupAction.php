<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\CdnSettingsResource;
use App\Http\Resources\Admin\Settings\GeneralSettingsResource;
use App\Http\Resources\Admin\Settings\ThirdPartySettingsResource;
use App\Settings\CdnSettings;
use App\Settings\GeneralSettings;
use App\Settings\ThirdPartySettings;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ShowSettingsGroupAction
{
    private const MAP = [
        'general' => [GeneralSettings::class, GeneralSettingsResource::class],
        'third_party' => [ThirdPartySettings::class, ThirdPartySettingsResource::class],
        'cdn' => [CdnSettings::class, CdnSettingsResource::class],
    ];

    public function handle(string $group): JsonResponse
    {
        if (! array_key_exists($group, self::MAP)) {
            return ApiResponse::error(__('setting.group_not_found'), [], 404);
        }

        $payload = Cache::tags(['settings'])->remember(
            CacheKeys::settings($group),
            CacheTtl::SETTINGS,
            function () use ($group): array {
                [$settingsClass, $resourceClass] = self::MAP[$group];

                return (new $resourceClass(app($settingsClass)))->resolve();
            }
        );

        return ApiResponse::success(data: $payload);
    }
}
