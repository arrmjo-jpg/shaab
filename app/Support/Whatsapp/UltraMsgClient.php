<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

use App\Settings\ThirdPartySettings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * عميل UltraMsg — REST مباشر عبر Http facade (نمط المشروع: GeminiTts/SportMonks، بلا SDK).
 * الإعدادات من Spatie ThirdPartySettings القائمة (instance/token/base_url). كل النداءات
 * مضبوطة المهلة وتعيد نتيجة منظَّمة بلا استثناءات — Jobs الإرسال تسجّل النتيجة كما هي.
 *
 * الأنواع الخمسة المطلوبة تُغطّى بثلاث نقاط: chat (نص) · image (+caption = صورة+نص) ·
 * video (+caption = فيديو+نص) — الوسيط مع النص رسالة واحدة (تسليم أفضل من رسالتين).
 */
final class UltraMsgClient
{
    private const TIMEOUT_SECONDS = 20;

    public function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }

    /** مفعَّل ومهيّأ (instance + token) — بوّابة كل إرسال. */
    public function isConfigured(): bool
    {
        $s = $this->settings();

        return $s->whatsapp_enabled
            && $s->whatsapp_instance_id !== ''
            && $s->whatsapp_token !== '';
    }

    public function sendText(string $to, string $body): WhatsappSendResult
    {
        return $this->post('messages/chat', ['to' => $to, 'body' => $body]);
    }

    public function sendImage(string $to, string $imageUrl, string $caption = ''): WhatsappSendResult
    {
        return $this->post('messages/image', ['to' => $to, 'image' => $imageUrl, 'caption' => $caption]);
    }

    public function sendVideo(string $to, string $videoUrl, string $caption = ''): WhatsappSendResult
    {
        return $this->post('messages/video', ['to' => $to, 'video' => $videoUrl, 'caption' => $caption]);
    }

    /**
     * فحص اتصال خفيف لزرّ «اختبار الاتصال»: حالة الـ instance.
     *
     * @return array{ok: bool, reason: ?string}
     */
    public function testConnection(): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->get($this->endpoint('instance/status'), ['token' => $this->settings()->whatsapp_token]);
        } catch (Throwable $e) {
            return ['ok' => false, 'reason' => $e->getMessage()];
        }

        $json = $response->json();
        if ($response->successful() && is_array($json) && ! isset($json['error'])) {
            return ['ok' => true, 'reason' => null];
        }

        return ['ok' => false, 'reason' => $this->errorFrom($response)];
    }

    private function post(string $path, array $params): WhatsappSendResult
    {
        if (! $this->isConfigured()) {
            return WhatsappSendResult::failure('whatsapp_not_configured');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->post($this->endpoint($path), ['token' => $this->settings()->whatsapp_token] + $params);
        } catch (Throwable $e) {
            return WhatsappSendResult::failure($e->getMessage());
        }

        $json = $response->json();

        // عقد UltraMsg: نجاح = sent=true (وغالباً id للرسالة)؛ فشل = مفتاح error نصّي/مصفوفة.
        $sent = is_array($json) ? ($json['sent'] ?? null) : null;
        if ($response->successful() && ($sent === 'true' || $sent === true)) {
            $id = is_array($json) && isset($json['id']) ? (string) $json['id'] : null;

            return WhatsappSendResult::success($id);
        }

        return WhatsappSendResult::failure($this->errorFrom($response));
    }

    private function endpoint(string $path): string
    {
        $s = $this->settings();
        $base = rtrim($s->whatsapp_base_url !== '' ? $s->whatsapp_base_url : 'https://api.ultramsg.com', '/');

        return $base.'/'.$s->whatsapp_instance_id.'/'.$path;
    }

    private function errorFrom(Response $response): string
    {
        $json = $response->json();
        if (is_array($json) && isset($json['error'])) {
            return is_string($json['error']) ? $json['error'] : json_encode($json['error'], JSON_UNESCAPED_UNICODE);
        }

        return 'HTTP '.$response->status();
    }
}
