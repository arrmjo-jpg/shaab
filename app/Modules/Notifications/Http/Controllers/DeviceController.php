<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Actions\RegisterDeviceAction;
use App\Modules\Notifications\Actions\ResolveDeviceTopicsAction;
use App\Modules\Notifications\Actions\UnregisterDeviceAction;
use App\Modules\Notifications\Actions\UpdateDeviceTokenAction;
use App\Modules\Notifications\Http\Requests\RegisterDeviceRequest;
use App\Modules\Notifications\Http\Requests\UpdateDeviceTokenRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نقاط جهاز الـpush (عامّة، bearer اختياريّ يربط user_id عبر حارس sanctum). تسجيل/تدوير توكن/
 * إلغاء + مزامنة topics (السيرفر يملك الحالة المرغوبة). الإرسال نفسه يبقى خلف NotificationManager.
 */
final class DeviceController extends Controller
{
    public function register(RegisterDeviceRequest $request): JsonResponse
    {
        $device = (new RegisterDeviceAction)->handle(
            $request->validated(),
            $request->user('sanctum')?->id,
        );

        return response()->json(['data' => ['device_id' => $device->device_id, 'is_active' => $device->is_active]], 201);
    }

    public function updateToken(UpdateDeviceTokenRequest $request): JsonResponse
    {
        $device = (new UpdateDeviceTokenAction)->handle(
            (string) $request->validated('device_id'),
            (string) $request->validated('fcm_token'),
        );

        if ($device === null) {
            return response()->json(['message' => 'device not found'], 404);
        }

        return response()->json(['data' => ['device_id' => $device->device_id]]);
    }

    public function unregister(string $deviceId): JsonResponse
    {
        (new UnregisterDeviceAction)->handle($deviceId);

        return response()->json(null, 204);
    }

    public function topics(Request $request): JsonResponse
    {
        $topics = (new ResolveDeviceTopicsAction)->handle($request->user('sanctum')?->id);

        return response()->json(['topics' => $topics]);
    }
}
