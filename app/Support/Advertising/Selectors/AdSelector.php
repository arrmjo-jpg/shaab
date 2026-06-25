<?php

declare(strict_types=1);

namespace App\Support\Advertising\Selectors;

/**
 * عقد اختيار الإعلان (server-side). الاختيار حتميّ بالكامل بالنسبة للبذرة + رقم الدلو
 * فيُطابق عبر كل عُقد الحافة/العمّال ويُمكّن كاش الحافة. قابل للتوسعة (مُحسِّن مستقبليّ).
 */
interface AdSelector
{
    /**
     * @param  array<int,array<string,mixed>>  $candidates  مرشّحون (placement_id, creative_id, type, weight)
     * @return array<string,mixed>|null المرشّح المختار أو null إن لا مرشّحين
     */
    public function select(array $candidates, int $bucket, string $seed): ?array;
}
