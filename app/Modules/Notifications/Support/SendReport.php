<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\AddressingModel;

/**
 * نتيجة إرسال مُهيكلة يُعيدها كلّ درايفر (لا يرمي استثناءً). تُغذّي عدّادات القناة في الحملة
 * وتقليم العناوين الميتة. topic ⇒ نتيجة واحدة (قبول النشر)؛ per_recipient ⇒ نتائج لكلّ مُستلِم.
 */
final class SendReport
{
    /** @param array<int,RecipientResult> $results */
    private function __construct(
        public readonly AddressingModel $mode,
        public readonly bool $accepted,
        public readonly int $sent,
        public readonly int $failed,
        public readonly int $invalid,
        public readonly array $results = [],
        public readonly ?string $providerRef = null,
        public readonly ?string $error = null,
        public readonly bool $skipped = false,
    ) {}

    /** القناة غير متوفّرة وقت الإرسال (بوّابة B) — تخطٍّ لا فشل. */
    public static function skipped(string $reason): self
    {
        return new self(AddressingModel::PerRecipient, false, 0, 0, 0, [], null, mb_substr($reason, 0, 1000), true);
    }

    public static function forTopic(bool $accepted, ?string $providerRef = null, ?string $error = null): self
    {
        return new self(
            AddressingModel::Topic,
            $accepted,
            $accepted ? 1 : 0,
            $accepted ? 0 : 1,
            0,
            [],
            $providerRef,
            $error !== null ? mb_substr($error, 0, 1000) : null,
        );
    }

    /** @param array<int,RecipientResult> $results */
    public static function forRecipients(array $results): self
    {
        $sent = 0;
        $failed = 0;
        $invalid = 0;
        foreach ($results as $r) {
            if ($r->invalid) {
                $invalid++;

                continue;
            }
            $r->ok ? $sent++ : $failed++;
        }

        return new self(AddressingModel::PerRecipient, $sent > 0, $sent, $failed, $invalid, array_values($results));
    }

    /** @return array<int,string> هويّات (ref) العناوين الميتة الواجب تقليمها */
    public function invalidRefs(): array
    {
        return array_values(array_map(
            fn (RecipientResult $r): string => $r->ref,
            array_filter($this->results, fn (RecipientResult $r): bool => $r->invalid),
        ));
    }
}
