<?php

declare(strict_types=1);

namespace App\Support\Tts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * عميل Google Gemini Text-to-Speech (REST) — يحوّل نصًّا إلى صوت WAV (data URI base64).
 * المفتاح من ThirdPartySettings (خادميّ بحت، لا يصل المتصفّح). الصوت الافتراضيّ أنثويّ (Kore).
 * المصادقة عبر ترويسة x-goog-api-key (القانونيّة). يلفّ PCM (16-bit/24kHz/mono) في حاوية WAV.
 * أي فشل ⇒ null؛ ويُسجَّل **جسم خطأ Google الفعليّ** (status + body) للتشخيص (السبب الحقيقيّ:
 * مفتاح/صلاحية/توفّر الموديل/شبكة/SSL) دون تسريب أي شيء للمستخدم.
 */
final class GeminiTts
{
    private const MODEL = 'gemini-2.5-flash-preview-tts';

    private const VOICE = 'Kore'; // صوت أنثويّ من أصوات Gemini المُسبقة (ينطق العربيّة حسب النصّ).

    public function synthesize(string $text, string $apiKey): ?string
    {
        if (trim($text) === '' || trim($apiKey) === '') {
            return null;
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout(60)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'.self::MODEL.':generateContent',
                    [
                        'contents' => [['parts' => [['text' => $text]]]],
                        'generationConfig' => [
                            'responseModalities' => ['AUDIO'],
                            'speechConfig' => [
                                'voiceConfig' => [
                                    'prebuiltVoiceConfig' => ['voiceName' => self::VOICE],
                                ],
                            ],
                        ],
                    ],
                );
        } catch (Throwable $e) {
            // غالبًا: شبكة/SSL (cURL) على الخادم المحلّي، أو مهلة.
            Log::warning('gemini tts request exception', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            // جسم Google يحوي السبب الدقيق (مفتاح غير صالح/الموديل غير متاح/تنسيق…).
            Log::warning('gemini tts non-ok response', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1500),
            ]);

            return null;
        }

        $inline = $response->json('candidates.0.content.parts.0.inlineData');
        $base64 = is_array($inline) ? ($inline['data'] ?? null) : null;
        if (! is_string($base64) || $base64 === '') {
            // 200 لكن بلا صوت (حظر أمان/شكل مختلف) — سجّل لقطة لمعرفة السبب.
            Log::warning('gemini tts ok but no audio', ['body' => mb_substr($response->body(), 0, 1500)]);

            return null;
        }

        $pcm = base64_decode($base64, true);
        if ($pcm === false || $pcm === '') {
            return null;
        }

        $mime = is_array($inline) ? (string) ($inline['mimeType'] ?? '') : '';
        $rate = preg_match('/rate=(\d+)/', $mime, $m) === 1 ? (int) $m[1] : 24000;

        return 'data:audio/wav;base64,'.base64_encode(self::pcmToWav($pcm, $rate));
    }

    /** يلفّ PCM 16-bit أحاديّ القناة (little-endian) في حاوية WAV (رأس 44 بايت). */
    private static function pcmToWav(string $pcm, int $rate, int $channels = 1, int $bits = 16): string
    {
        $byteRate = $rate * $channels * intdiv($bits, 8);
        $blockAlign = $channels * intdiv($bits, 8);
        $dataLen = strlen($pcm);

        return 'RIFF'.pack('V', 36 + $dataLen).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', $channels)
            .pack('V', $rate).pack('V', $byteRate).pack('v', $blockAlign).pack('v', $bits)
            .'data'.pack('V', $dataLen).$pcm;
    }
}
