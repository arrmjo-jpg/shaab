<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\ThirdPartySettingsResource;
use App\Settings\ThirdPartySettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateThirdPartySettingsAction
{
    public function handle(array $validated): JsonResponse
    {
        $settings = app(ThirdPartySettings::class);
        $secrets = ThirdPartySettings::encrypted();

        foreach ($validated as $key => $value) {
            if (in_array($key, $secrets, true)
                && ($value === null || $value === '' || $value === '********')) {
                continue;
            }

            $settings->{$key} = $value ?? '';
        }

        $settings->save();
        SettingsAudit::log('third_party', array_keys($validated), $secrets);

        Cache::tags(['settings'])->flush();
        // إبطال واجهة Next: بوّابات الميزات المُكاشة (tts/social/recaptcha config) تتغذّى من هذه المجموعة.
        FrontendRevalidate::tags(['tts-config', 'social-config', 'recaptcha-config']);

        return ApiResponse::success(
            __('setting.updated'),
            new ThirdPartySettingsResource($settings)
        );
    }
}
