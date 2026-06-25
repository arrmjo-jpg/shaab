<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\UltraMsgClient;
use Illuminate\Http\JsonResponse;

/**
 * اختبار اتصال واتساب (UltraMsg) — يتحقق من حالة الـ instance بالإعدادات المخزَّنة.
 * مرآة TestSportmonksConnectionAction (زرّ اختبار في صفحة إعدادات واتساب).
 */
class TestWhatsappConnectionAction
{
    public function handle(): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        if ($s->whatsapp_instance_id === '' || $s->whatsapp_token === '') {
            return ApiResponse::error(__('setting.integration_key_missing'), [], 422);
        }

        $result = (new UltraMsgClient)->testConnection();

        if ($result['ok'] === true) {
            return ApiResponse::success(__('setting.whatsapp_test_success'));
        }

        return ApiResponse::error(__('setting.whatsapp_test_failed'), [
            'reason' => $result['reason'],
        ], 422);
    }
}
