<?php

declare(strict_types=1);

namespace App\Support\Advertising\Selectors;

/**
 * توزيع متساوٍ — اختيار منتظم حتميّ بالبذرة (يتجاهل الوزن)، يمنح كل مرشّح فرصة متساوية
 * عبر الدلاء. يختلف عن التناوب التسلسليّ: الاختيار مبعثر-منتظم لا متسلسل.
 */
final class EvenSelector implements AdSelector
{
    public function select(array $candidates, int $bucket, string $seed): ?array
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $a, array $b): int => ($a['creative_id'] ?? 0) <=> ($b['creative_id'] ?? 0));

        $idx = (crc32($seed) & 0x7FFFFFFF) % count($candidates);

        return $candidates[$idx];
    }
}
