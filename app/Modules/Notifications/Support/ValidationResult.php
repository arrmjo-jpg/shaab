<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/** نتيجة تحقّق الدرايفر من صلاحيّة الرسالة لقناته (قبل الإرسال). */
final class ValidationResult
{
    /** @param array<int,string> $errors */
    private function __construct(
        public readonly bool $ok,
        public readonly array $errors = [],
    ) {}

    public static function valid(): self
    {
        return new self(true);
    }

    /** @param array<int,string> $errors */
    public static function invalid(array $errors): self
    {
        return new self(false, array_values($errors));
    }
}
