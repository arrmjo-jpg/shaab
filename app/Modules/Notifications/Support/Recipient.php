<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/**
 * مُستلِم واحد قابل للعنونة على قناة per_recipient. ref = هويّة ثابتة للتتبّع/التقليم
 * ("user:123" | "device:uuid" | "contact:45")، address = العنوان الفعليّ (توكن FCM | بريد | E.164).
 */
final class Recipient
{
    public function __construct(
        public readonly string $ref,
        public readonly string $address,
        public readonly ?string $locale = null,
    ) {}
}
