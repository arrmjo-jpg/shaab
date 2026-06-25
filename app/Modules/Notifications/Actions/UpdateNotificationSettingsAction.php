<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Http\Resources\NotificationSettingsResource;
use App\Settings\NotificationSettings;
use App\Support\Audit\SettingsAudit;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * يُحدّث إعدادات الإشعارات (Spatie Settings). يُسجّل التدقيق يدويًّا (أسماء المفاتيح فقط، لا أسرار
 * في هذه المجموعة) عبر SettingsAudit — Rule 3 من اصطلاح التدقيق.
 */
final class UpdateNotificationSettingsAction
{
    /** @param  array<string,mixed>  $validated */
    public function handle(array $validated): JsonResponse
    {
        $settings = app(NotificationSettings::class);

        foreach ($validated as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        SettingsAudit::log('notifications', array_keys($validated));

        Cache::tags(['settings'])->flush();

        return ApiResponse::success(
            message: 'تم تحديث إعدادات الإشعارات',
            data: (new NotificationSettingsResource($settings))->resolve(),
        );
    }
}
