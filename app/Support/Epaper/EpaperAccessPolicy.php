<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Models\Epaper;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * عقد الوصول للعدد — مصدر القرار الوحيد (canView/canDownload). يُربط في الحاوية
 * بسياسة افتراضية، ويعيد المضيف ربطه لدمج منطق الاشتراك/الاستحقاق الفعليّ (لا محرّك
 * اشتراكات في AlphaCMS — هذا هو مَوْصِل التكامل). $user قد يكون null (زائر عامّ).
 */
interface EpaperAccessPolicy
{
    public function canView(?Authenticatable $user, Epaper $issue): bool;

    public function canDownload(?Authenticatable $user, Epaper $issue): bool;
}
