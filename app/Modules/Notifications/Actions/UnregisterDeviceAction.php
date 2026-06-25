<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\MobileDevice;

/** إلغاء تسجيل صريح (إلغاء اشتراك/إزالة) ⇒ is_active=false. إعادة التسجيل تُعيد التفعيل. */
final class UnregisterDeviceAction
{
    public function handle(string $deviceId): void
    {
        MobileDevice::query()->where('device_id', $deviceId)->update(['is_active' => false]);
    }
}
