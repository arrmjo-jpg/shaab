<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Broadcast\BroadcastSourceValidator;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * يتحقّق أن رابط مصدر البثّ موثوق وآمن للنوع المُرسَل (source_type المجاور) — https
 * + مضيف ضمن القائمة الموثوقة لذلك النوع. يقرأ source_type عبر DataAwareRule.
 */
class ResolvableBroadcastSourceUrl implements DataAwareRule, ValidationRule
{
    /** @var array<string,mixed> */
    private array $data = [];

    /** @param array<string,mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $sourceType = (string) ($this->data['source_type'] ?? '');

        if (! is_string($value) || ! BroadcastSourceValidator::isAllowed($sourceType, $value)) {
            $fail(__('broadcast.source.unsupported'));
        }
    }
}
