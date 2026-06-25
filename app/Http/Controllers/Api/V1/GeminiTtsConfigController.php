<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إعداد Gemini TTS العامّ (بلا مصادقة، بلا أسرار) — يُرجع فقط توفّر الميزة (مُفعَّلة + مفتاح
 * مضبوط) لتقرّر الواجهة إظهار زرّ «الاستماع للمقال». لا يكشف المفتاح إطلاقًا.
 */
class GeminiTtsConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        return ApiResponse::success(data: [
            'enabled' => $s->gemini_tts_enabled && $s->gemini_tts_api_key !== '',
        ]);
    }
}
