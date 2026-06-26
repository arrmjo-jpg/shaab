<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Content\TipTapSanitizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * يرفض أي مستند TipTap يحوي عقدة/علامة/سمة غير مسموحة (P4-D1).
 */
class ValidTipTapDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! TipTapSanitizer::validate($value)) {
            $fail(__('article.invalid_content'));
        }
    }
}
