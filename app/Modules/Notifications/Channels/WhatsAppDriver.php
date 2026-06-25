<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Channels;

use App\Modules\Notifications\Contracts\ChannelDriver;
use App\Modules\Notifications\Enums\AddressingModel;
use App\Modules\Notifications\Enums\ChannelKey;
use App\Modules\Notifications\Enums\DeepLinkType;
use App\Modules\Notifications\Support\ChannelCapabilities;
use App\Modules\Notifications\Support\ChannelHealth;
use App\Modules\Notifications\Support\ChannelMessage;
use App\Modules\Notifications\Support\RecipientBatch;
use App\Modules\Notifications\Support\RecipientResult;
use App\Modules\Notifications\Support\SendReport;
use App\Modules\Notifications\Support\ValidationResult;
use App\Settings\ThirdPartySettings;
use App\Support\Whatsapp\UltraMsgClient;

/**
 * درايفر WhatsApp — **محوّل نقل فقط** يلفّ UltraMsgClient القائم (لا إعادة تنفيذ، لا نسخ
 * counters/recipients/lifecycle). per_recipient فقط (لا topic). يستقبل RecipientBatch[هواتف]
 * ويُرسل لكلّ رقم عبر العميل، ويُعيد SendReport. التوكنات الميتة لا تُطبَّق (هاتف لا توكن جهاز).
 */
final class WhatsAppDriver implements ChannelDriver
{
    public function __construct(private readonly UltraMsgClient $client = new UltraMsgClient) {}

    public function key(): ChannelKey
    {
        return ChannelKey::Whatsapp;
    }

    public function addressing(): AddressingModel
    {
        return AddressingModel::PerRecipient;
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(bulk: true, media: true, html: false, deeplink: false, maxBatch: 50, topic: false);
    }

    public function enabled(): bool
    {
        return $this->settings()->whatsapp_enabled;
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured(); // enabled + instance + token
    }

    public function health(): ChannelHealth
    {
        $s = $this->settings();
        if ($s->whatsapp_instance_id === '' || $s->whatsapp_token === '') {
            return ChannelHealth::unconfigured();
        }

        $start = microtime(true);
        $result = $this->client->testConnection(); // probe خفيف (نمط زرّ «اختبار الاتصال» القائم)
        $latency = (int) ((microtime(true) - $start) * 1000);

        return ($result['ok'] ?? false)
            ? ChannelHealth::healthy($latency)
            : ChannelHealth::problem((string) ($result['reason'] ?? 'connection failed'), $latency);
    }

    public function validate(ChannelMessage $message): ValidationResult
    {
        return trim($message->title) === '' && trim($message->body) === ''
            ? ValidationResult::invalid(['whatsapp: message body required'])
            : ValidationResult::valid();
    }

    public function send(ChannelMessage $message, RecipientBatch $recipients, string $idempotencyKey): SendReport
    {
        if (! $this->isConfigured()) {
            return SendReport::skipped('whatsapp not configured');
        }

        $text = $this->text($message);
        $results = [];
        foreach ($recipients->recipients as $recipient) {
            $result = $message->imageUrl !== null
                ? $this->client->sendImage($recipient->address, $message->imageUrl, $text)
                : $this->client->sendText($recipient->address, $text);

            $results[] = $result->ok
                ? RecipientResult::sent($recipient->ref, $result->providerMessageId)
                : RecipientResult::failed($recipient->ref, (string) $result->error);
        }

        return SendReport::forRecipients($results);
    }

    private function text(ChannelMessage $message): string
    {
        $text = trim(implode("\n\n", array_filter([$message->title, $message->body])));

        if ($message->deepLink !== null && $message->deepLink->type !== DeepLinkType::None && $message->deepLink->value !== null) {
            $text .= "\n".$message->deepLink->value;
        }

        return $text;
    }

    private function settings(): ThirdPartySettings
    {
        return app(ThirdPartySettings::class);
    }
}
