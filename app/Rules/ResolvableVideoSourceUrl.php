<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Video\VideoSourceResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * يتحقّق أن الرابط مصدر فيديو مدعوم في مكتبة الفيديو (youtube|vimeo|direct_mp4)
 * وآمن (direct_mp4 يخضع لـ allow-list صارمة). يرفض بقية المزوّدين والمضيفات.
 */
class ResolvableVideoSourceUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! VideoSourceResolver::isValid($value)) {
            $fail(__('video.source.unsupported'));
        }
    }
}
