<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestSportmonksConnectionAction
{
    public function handle(): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        if ($s->sportmonks_api_key === '') {
            return ApiResponse::error(__('setting.integration_key_missing'), [], 422);
        }

        try {
            // نقطة خفيفة فقط
            $response = Http::acceptJson()
                ->timeout(8)
                ->get(rtrim($s->sportmonks_base_url, '/').'/core/continents', [
                    'api_token' => $s->sportmonks_api_key,
                ]);
        } catch (Throwable $e) {
            Log::warning('SportMonks test failed', ['error' => $e->getMessage()]);

            return ApiResponse::error(__('setting.sportmonks_test_failed'), ['reason' => $e->getMessage()], 422);
        }

        if ($response->successful()) {
            return ApiResponse::success(__('setting.sportmonks_test_success'));
        }

        return ApiResponse::error(__('setting.sportmonks_test_failed'), [
            'reason' => 'HTTP '.$response->status(),
        ], 422);
    }
}
