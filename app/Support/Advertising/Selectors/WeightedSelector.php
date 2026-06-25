<?php

declare(strict_types=1);

namespace App\Support\Advertising\Selectors;

/**
 * اختيار موزون حتميّ بالوزن الصريح (placement/creative) — ليس بالنقرات. نقطة عشوائية
 * مشتقّة من البذرة (crc32) ضمن مجموع الأوزان ⇒ نفس البذرة تُنتج نفس الاختيار (ثبات
 * ضمن الدلو)، وتغيّر الدلو يدوّر بتوزيع متناسب مع الوزن.
 */
final class WeightedSelector implements AdSelector
{
    public function select(array $candidates, int $bucket, string $seed): ?array
    {
        if ($candidates === []) {
            return null;
        }

        $total = 0;
        foreach ($candidates as $c) {
            $total += max(1, (int) ($c['weight'] ?? 1));
        }

        $point = (int) floor(self::unit($seed) * $total); // 0..total-1
        $acc = 0;
        foreach ($candidates as $c) {
            $acc += max(1, (int) ($c['weight'] ?? 1));
            if ($point < $acc) {
                return $c;
            }
        }

        return $candidates[array_key_last($candidates)];
    }

    /** crc32(seed) → [0,1) حتميّ (موجب عبر قناع 31-بت). */
    private static function unit(string $seed): float
    {
        return (crc32($seed) & 0x7FFFFFFF) / 2147483648.0;
    }
}
