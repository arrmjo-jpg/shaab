<?php

declare(strict_types=1);

namespace App\Modules\CDN\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * حالة CDN. المورد resource هنا مصفوفة محضّرة في الـ Action.
 */
class CdnStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'enabled' => $this->resource['enabled'],
            'configured' => $this->resource['configured'],
            'plan' => $this->resource['plan'],
            'auto_purge' => $this->resource['auto_purge'],
            'stats' => $this->resource['stats'],
        ];
    }
}
