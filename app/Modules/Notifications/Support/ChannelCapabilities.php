<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/** قدرات قناة — يصرّح بها الدرايفر ليعرف المُنسّق كيف يبني الرسالة والدفعة. */
final class ChannelCapabilities
{
    public function __construct(
        public readonly bool $bulk,
        public readonly bool $media,
        public readonly bool $html,
        public readonly bool $deeplink,
        public readonly int $maxBatch,
        public readonly bool $topic,
    ) {}
}
