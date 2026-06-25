<?php

declare(strict_types=1);

namespace App\Modules\CDN\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * إعدادات CDN — التوكن مُقنَّع دائماً.
 */
class CdnSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;
        $tokenSet = $s->cdn_api_token !== null && $s->cdn_api_token !== '';

        return [
            'cdn_enabled' => $s->cdn_enabled,
            'auto_purge' => $s->cdn_auto_purge,
            'plan' => $s->cdn_plan,
            'zone_id' => $s->cdn_zone_id,
            'api_token' => $tokenSet ? '********' : null,
            'api_token_configured' => $tokenSet,
        ];
    }
}
