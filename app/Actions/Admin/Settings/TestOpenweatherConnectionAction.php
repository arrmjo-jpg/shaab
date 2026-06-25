<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TestOpenweatherConnectionAction
{
    public function handle(): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        if ($s->openweather_api_key === '') {
            return ApiResponse::error(__('setting.integration_key_missing'), [], 422);
        }

        try {
            // One Call API 3.0 — يتطلب lat/lon (لا يقبل q=city)
            $response = Http::acceptJson()
                ->timeout(8)
                ->get(rtrim($s->openweather_base_url, '/').'/onecall', [
                    'lat' => 24.7136,
                    'lon' => 46.6753,
                    'appid' => $s->openweather_api_key,
                    'units' => $s->openweather_units,
                    'exclude' => 'minutely,hourly,daily,alerts',
                ]);
        } catch (Throwable $e) {
            Log::warning('OpenWeather test failed', ['error' => $e->getMessage()]);

            return ApiResponse::error(__('setting.openweather_test_failed'), ['reason' => $e->getMessage()], 422);
        }

        if ($response->successful()) {
            return ApiResponse::success(__('setting.openweather_test_success'));
        }

        Log::warning('OpenWeather test failed', [
            'status' => $response->status(),
            'message' => $response->json('message'),
        ]);

        return ApiResponse::error(__('setting.openweather_test_failed'), [
            'reason' => 'HTTP '.$response->status().': '.(string) $response->json('message', ''),
        ], 422);
    }
}
