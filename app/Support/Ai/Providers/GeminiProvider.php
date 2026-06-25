<?php

declare(strict_types=1);

namespace App\Support\Ai\Providers;

use App\Contracts\Ai\AiProvider;
use App\Settings\ThirdPartySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * مزوّد Google Gemini (Generative Language API). يقرأ المفتاح/النموذج من
 * إعدادات اللوحة. يحوّل رسائل النمط الموحّد (system/user/assistant) إلى عقد
 * Gemini (systemInstruction + contents بأدوار user/model).
 */
final class GeminiProvider implements AiProvider
{
    private function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }

    public function configured(): bool
    {
        return $this->settings()->gemini_api_key !== '';
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function chat(array $messages, array $options = []): string
    {
        $s = $this->settings();

        $system = '';
        $contents = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system = trim($system."\n".$m['content']);

                continue;
            }
            // Gemini يستخدم الدور "model" بدل "assistant".
            $contents[] = [
                'role' => ($m['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ];
        }

        $generationConfig = [
            'temperature' => $options['temperature'] ?? $s->gemini_temperature,
            'maxOutputTokens' => (int) ($options['max_tokens'] ?? $s->gemini_max_tokens),
        ];
        if (! empty($options['json'])) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        $payload = ['contents' => $contents, 'generationConfig' => $generationConfig];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $model = $s->gemini_model !== '' ? $s->gemini_model : 'gemini-2.0-flash';
        $base = rtrim((string) config('ai.gemini_base_url'), '/');

        try {
            // مفتاح المصادقة في ترويسة (x-goog-api-key) لا في الـ query string — يمنع
            // تسريبه إلى سجلّات الوصول/الوسطاء (نظافة أمنية).
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $s->gemini_api_key])
                ->timeout($s->gemini_timeout > 0 ? $s->gemini_timeout : 30)
                ->post($base.'/models/'.$model.':generateContent', $payload);
        } catch (Throwable $e) {
            Log::warning('AI(gemini) transport failure', ['error' => $e->getMessage()]);
            throw new RuntimeException('ai_transport_failure', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('AI(gemini) provider error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
                'model' => $model,
            ]);
            throw new RuntimeException('ai_provider_error_'.$response->status());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('ai_empty_response');
        }

        return $text;
    }
}
