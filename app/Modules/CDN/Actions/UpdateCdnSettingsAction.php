<?php

declare(strict_types=1);

namespace App\Modules\CDN\Actions;

use App\Modules\CDN\Resources\CdnSettingsResource;
use App\Settings\CdnSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateCdnSettingsAction
{
    private const SECRETS = ['cdn_api_token'];

    public function handle(array $validated): JsonResponse
    {
        $settings = app(CdnSettings::class);

        foreach ($validated as $key => $value) {
            // لا تستبدل التوكن الموجود بقناع/فارغ
            if (in_array($key, self::SECRETS, true)
                && ($value === null || $value === '' || $value === '********')) {
                continue;
            }

            $settings->{$key} = $value ?? '';
        }

        $settings->save(); // cdn_api_token مشفّر عبر CdnSettings::encrypted()

        // إعداد CDN يقع ضمن مجموعة settings؛ وحالة CDN ضمن مجموعة cdn
        Cache::tags(['settings'])->flush();
        Cache::tags(['cdn'])->flush();

        return ApiResponse::success(
            __('cdn.settings_updated'),
            new CdnSettingsResource($settings)
        );
    }
}
