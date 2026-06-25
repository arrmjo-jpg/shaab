<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Support\Media\RemoteStorage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * «مزامنة الآن» — يُجدوِل وظائف نسخ المتراكم التشغيلي عبر أمر media:sync:remote
 * (يُرسل وظائف idempotent للطابور؛ لا عمل ثقيل في الطلب). يتطلّب تفعيل المرآة.
 */
class SyncRemoteMediaNowAction
{
    public function handle(): JsonResponse
    {
        if (! RemoteStorage::enabled()) {
            return ApiResponse::error(__('setting.media_remote_disabled'), [], 422);
        }

        Artisan::call('media:sync:remote');

        return ApiResponse::success(__('setting.media_sync_started'));
    }
}
