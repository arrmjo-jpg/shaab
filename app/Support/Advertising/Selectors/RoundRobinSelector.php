<?php

declare(strict_types=1);

namespace App\Support\Advertising\Selectors;

/**
 * تناوب تسلسليّ عادل — يدور المرشّحون بالترتيب عبر الدلاء (bucket % n) بعد ترتيب
 * ثابت بـ creative_id كي يكون التسلسل متطابقاً عبر كل العُقد. يتجاهل الوزن (تناوب صرف).
 */
final class RoundRobinSelector implements AdSelector
{
    public function select(array $candidates, int $bucket, string $seed): ?array
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b): int => ($a['creative_id'] ?? 0) <=> ($b['creative_id'] ?? 0));

        return $candidates[$bucket % count($candidates)];
    }
}
