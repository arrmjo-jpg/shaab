<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\MediaStorageSettingsResource;
use App\Settings\MediaStorageSettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Media\RemoteStorageHealth;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UpdateMediaStorageSettingsAction
{
    public function handle(array $validated): JsonResponse
    {
        $settings = app(MediaStorageSettings::class);
        $secrets = MediaStorageSettings::encrypted();

        foreach ($validated as $key => $value) {
            // لا تُحدِّث سرّاً مُقنَّعاً/فارغاً (يبقى المحفوظ) — حماية الأسرار.
            if (in_array($key, $secrets, true)
                && ($value === null || $value === '' || $value === '********')) {
                continue;
            }

            $settings->{$key} = $value ?? '';
        }

        $settings->save();

        // تدقيق يدوي — أسماء المفاتيح فقط، الأسرار مستثناة (Rule 3).
        SettingsAudit::log('media', array_keys($validated), $secrets);

        // قد تتغيّر الاعتماديات/التفعيل ⇒ أبطِل كاش صحّة المرآة كي يُعاد فحصها.
        RemoteStorageHealth::flush();
        Cache::tags(['settings'])->flush();

        return ApiResponse::success(
            __('setting.updated'),
            new MediaStorageSettingsResource($settings->refresh())
        );
    }
}
