<?php

declare(strict_types=1);

namespace App\Actions\Public\Tts;

use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use App\Support\Tts\GeminiTts;
use Illuminate\Http\JsonResponse;

/**
 * توليد صوت المقال عبر Gemini TTS. الميزة محكومة بإعدادات Spatie:
 *   - مُعطَّلة أو بلا مفتاح ⇒ 404 (الميزة «غير موجودة» للمستخدم، بلا خطأ مُربك).
 *   - فشل التوليد ⇒ 502.
 * المفتاح يبقى خادميًّا (لا يصل المتصفّح).
 */
class GenerateSpeechAction
{
    public function handle(string $text): JsonResponse
    {
        $s = app(ThirdPartySettings::class);

        if (! $s->gemini_tts_enabled || $s->gemini_tts_api_key === '') {
            return ApiResponse::error(__('tts.unavailable'), [], 404);
        }

        $audio = (new GeminiTts)->synthesize($text, $s->gemini_tts_api_key);
        if ($audio === null) {
            return ApiResponse::error(__('tts.failed'), [], 502);
        }

        return ApiResponse::success(data: ['audio' => $audio]);
    }
}
