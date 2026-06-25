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

class ShowSettingsOverviewAction
{
    public function handle(): JsonResponse
    {
        return ApiResponse::success(data: [
            'general' => Cache::tags(['settings'])->remember(
                CacheKeys::settings('general'),
                CacheTtl::SETTINGS,
                fn (): array => (new GeneralSettingsResource(app(GeneralSettings::class)))->resolve()
            ),
            'third_party' => Cache::tags(['settings'])->remember(
                CacheKeys::settings('third_party'),
                CacheTtl::SETTINGS,
                fn (): array => (new ThirdPartySettingsResource(app(ThirdPartySettings::class)))->resolve()
            ),
            'cdn' => Cache::tags(['settings'])->remember(
                CacheKeys::settings('cdn'),
                CacheTtl::SETTINGS,
                fn (): array => (new CdnSettingsResource(app(CdnSettings::class)))->resolve()
            ),
        ]);
    }
}
