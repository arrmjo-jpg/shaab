<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\CdnSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestCdnConnectionAction
{
    private const VERIFY_URL = 'https://api.cloudflare.com/client/v4/user/tokens/verify';

    public function handle(): JsonResponse
    {
        $token = app(CdnSettings::class)->cdn_api_token;

        if ($token === null || $token === '') {
            return ApiResponse::error(__('setting.cdn_token_missing'), [], 422);
        }

        try {
            // اختبار خفيف: التحقق من صلاحية الـ token فقط
            $response = Http::withToken($token)
                ->timeout(8)
                ->acceptJson()
                ->get(self::VERIFY_URL);
        } catch (Throwable) {
            return ApiResponse::error(__('setting.cdn_test_failed'), [], 422);
        }

        if ($response->successful() && $response->json('success') === true) {
            return ApiResponse::success(__('setting.cdn_test_success'));
        }

        return ApiResponse::error(__('setting.cdn_test_failed'), [], 422);
    }
}
