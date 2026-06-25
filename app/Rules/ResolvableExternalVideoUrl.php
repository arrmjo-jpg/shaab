<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Media\ExternalVideoResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * يتحقّق أن الرابط يُحَلّ إلى مزوّد فيديو خارجي مدعوم (allow-list).
 */
class ResolvableExternalVideoUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ExternalVideoResolver::resolve($value) === null) {
            $fail(__('media.external.unsupported'));
        }
    }
}
