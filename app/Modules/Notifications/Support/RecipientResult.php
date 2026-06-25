<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * نتيجة إرسال لمُستلِم واحد. invalid=true ⇒ عنوان/توكن ميت يُقلَّم (لا يُحسَب فشلاً للحملة).
 */
final class RecipientResult
{
    private function __construct(
        public readonly string $ref,
        public readonly bool $ok,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        public readonly bool $invalid = false,
    ) {}

    public static function sent(string $ref, ?string $providerMessageId = null): self
    {
        return new self($ref, true, $providerMessageId);
    }

    public static function failed(string $ref, string $error): self
    {
        return new self($ref, false, null, mb_substr($error, 0, 1000));
    }

    public static function invalid(string $ref, string $error = 'invalid address'): self
    {
        return new self($ref, false, null, mb_substr($error, 0, 1000), true);
    }
}
