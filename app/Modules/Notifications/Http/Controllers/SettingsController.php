<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Actions\UpdateNotificationSettingsAction;
use App\Modules\Notifications\Http\Requests\UpdateNotificationSettingsRequest;
use App\Modules\Notifications\Http\Resources\NotificationSettingsResource;
use App\Settings\NotificationSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعدادات مركز الإشعارات — Kill Switch (enabled/critical_bypass) + Quiet Hours. القراءة view،
 * التعديل manage (مُدقَّق عبر SettingsAudit). مجموعة مقصودة الحدّ الأدنى (لا toggles per-channel).
 */
final class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(
            data: (new NotificationSettingsResource(app(NotificationSettings::class)))->resolve(),
        );
    }

    public function update(UpdateNotificationSettingsRequest $request, UpdateNotificationSettingsAction $action): JsonResponse
    {
        return $action->handle($request->validated());
    }
}
