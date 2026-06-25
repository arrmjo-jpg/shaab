<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\CdnSettingsResource;
use App\Settings\CdnSettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateCdnSettingsAction
{
    public function handle(array $validated): JsonResponse
    {
        $settings = app(CdnSettings::class);
        $secrets = CdnSettings::encrypted();

        foreach ($validated as $key => $value) {
            if (in_array($key, $secrets, true)
                && ($value === null || $value === '' || $value === '********')) {
                continue;
            }

            $settings->{$key} = $value ?? '';
        }

        $settings->save();
        SettingsAudit::log('cdn', array_keys($validated), $secrets);

        Cache::tags(['settings'])->flush();

        return ApiResponse::success(
            __('setting.updated'),
            new CdnSettingsResource($settings)
        );
    }
}
