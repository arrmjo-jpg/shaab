<?php

declare(strict_types=1);

namespace App\Support\Whatsapp;

/**
 * نتيجة إرسال رسالة واتساب واحدة عبر UltraMsg — كائن غير قابل للتغيير تستهلكه Jobs
 * الإرسال لتحديث سجلّ الرسالة (نجاح + معرّف المزوّد، أو فشل + السبب). لا استثناءات.
 */
final class WhatsappSendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function success(?string $providerMessageId): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, mb_substr($error, 0, 1000));
    }
}
