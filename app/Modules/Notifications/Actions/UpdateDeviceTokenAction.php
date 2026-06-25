<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\MobileDevice;

/** تدوير FCM token لجهاز قائم. غير موجود ⇒ null (يُعيد التطبيق التسجيل). لا يمسّ user_id. */
final class UpdateDeviceTokenAction
{
    public function handle(string $deviceId, string $token): ?MobileDevice
    {
        $device = MobileDevice::query()->where('device_id', $deviceId)->first();
        if ($device === null) {
            return null;
        }

        $device->fcm_token = $token;
        $device->is_active = true;
        $device->last_seen_at = now();
        $device->save();

        return $device;
    }
}
