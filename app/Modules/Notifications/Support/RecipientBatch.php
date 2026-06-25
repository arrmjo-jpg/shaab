<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\AddressingModel;

/**
 * دفعة مستلمين تُسلَّم للدرايفر: إمّا قائمة عناوين (per_recipient) أو اسم topic واحد.
 * يحلّها المُنسّق من الجمهور؛ الدرايفر لا يعرف كيف اشتُقّت.
 */
final class RecipientBatch
{
    /** @param array<int,Recipient> $recipients */
    private function __construct(
        public readonly AddressingModel $mode,
        public readonly array $recipients = [],
        public readonly ?string $topic = null,
    ) {}

    /** @param array<int,Recipient> $recipients */
    public static function forRecipients(array $recipients): self
    {
        return new self(AddressingModel::PerRecipient, array_values($recipients));
    }

    public static function forTopic(string $topic): self
    {
        return new self(AddressingModel::Topic, [], $topic);
    }

    public function count(): int
    {
        return $this->mode === AddressingModel::Topic ? 1 : count($this->recipients);
    }

    public function isEmpty(): bool
    {
        return $this->mode === AddressingModel::PerRecipient && $this->recipients === [];
    }
}
