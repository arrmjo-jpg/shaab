<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\GeneralSettingsResource;
use App\Settings\GeneralSettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateGeneralSettingsAction
{
    public function handle(array $validated): JsonResponse
    {
        $settings = app(GeneralSettings::class);
        $secrets = GeneralSettings::encrypted();

        foreach ($validated as $key => $value) {
            // لا تستبدل سرّاً موجوداً بقيمة فارغة أو القناع
            if (in_array($key, $secrets, true)
                && ($value === null || $value === '' || $value === '********')) {
                continue;
            }

            $settings->{$key} = $value ?? '';
        }

        $settings->save(); // spatie يشفّر حقول encrypted() تلقائياً
        SettingsAudit::log('general', array_keys($validated), $secrets);

        Cache::tags(['settings'])->flush();
        // إبطال واجهة Next: /site (هيدر/فوتر/SEO/شعارات) يتغذّى من هذه الإعدادات.
        FrontendRevalidate::tags(['site-settings']);

        return ApiResponse::success(
            __('setting.updated'),
            new GeneralSettingsResource($settings)
        );
    }
}
