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
 * مزوّد OpenAI Chat Completions. يقرأ المفتاح/النموذج/المعاملات من إعدادات
 * اللوحة (ThirdPartySettings) — لا .env. مجرّد ناقل، لا يبني أي prompt.
 */
final class OpenAiProvider implements AiProvider
{
    private function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }

    public function configured(): bool
    {
        return $this->settings()->openai_api_key !== '';
    }

    public function name(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): string
    {
        $s = $this->settings();

        $payload = [
            'model' => $s->openai_model !== '' ? $s->openai_model : 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $s->openai_temperature,
            'max_tokens' => (int) ($options['max_tokens'] ?? $s->openai_max_tokens),
        ];

        // ملاحظة: لا نفرض response_format=json_object — فهو غير مدعوم على بعض
        // النماذج/البروكسيات (يُسبّب 400). التلقين يطلب JSON والمحلّل متسامح.

        $base = $s->openai_base_url !== '' ? $s->openai_base_url : 'https://api.openai.com/v1';

        try {
            $response = Http::withToken($s->openai_api_key)
                ->acceptJson()
                ->timeout($s->openai_timeout > 0 ? $s->openai_timeout : 30)
                ->post(rtrim($base, '/').'/chat/completions', $payload);
        } catch (Throwable $e) {
            Log::warning('AI(openai) transport failure', ['error' => $e->getMessage()]);
            throw new RuntimeException('ai_transport_failure', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('AI(openai) provider error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
                'model' => $payload['model'],
            ]);
            throw new RuntimeException('ai_provider_error_'.$response->status());
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('ai_empty_response');
        }

        return $text;
    }
}
