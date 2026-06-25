<?php

declare(strict_types=1);

namespace App\Modules\CDN\Support;

/**
 * أسماء طوابير الوحدة (module-local — لا تعديل config/performance).
 */
final class CdnQueues
{
    public const PURGE = 'cdn-purge';
}
