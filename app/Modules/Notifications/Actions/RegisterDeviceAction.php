<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\MobileDevice;

/**
 * تسجيل/upsert جهاز بالـdevice_id. user_id = حالة المصادقة الحاليّة (bearer ⇒ مرتبط، none ⇒ ضيف)
 * — يوحّد دورة الحياة: الدخول (register بـbearer)، الخروج (register بلا bearer ⇒ ضيف)، تبديل الحساب.
 */
final class RegisterDeviceAction
{
    /** @param  array{device_id:string,fcm_token:string,platform:string,locale?:string|null}  $data */
    public function handle(array $data, ?int $userId): MobileDevice
    {
        $device = MobileDevice::query()->firstOrNew(['device_id' => $data['device_id']]);
        $device->user_id = $userId;
        $device->platform = $data['platform'];
        $device->fcm_token = $data['fcm_token'];
        if (array_key_exists('locale', $data) && $data['locale'] !== null && $data['locale'] !== '') {
            $device->locale = $data['locale'];
        }
        $device->is_active = true;
        $device->last_seen_at = now();
        $device->save();

        return $device;
    }
}
