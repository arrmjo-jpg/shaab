<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد إعدادات التخزين الهجين (المرآة البعيدة). الأسرار (المفتاح/السرّ) مُقنَّعة
 * أبداً لا تُكشف قيمتها — يُكتفى بعلم «مضبوط» للواجهة.
 */
class MediaStorageSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $s = $this->resource;
        $keySet = $s->remote_key !== '';
        $secretSet = $s->remote_secret !== '';

        return [
            'remote_enabled' => $s->remote_enabled,
            'remote_driver' => $s->remote_driver, // واجهة: "S3-compatible"
            'remote_key' => $keySet ? '********' : null,
            'remote_key_configured' => $keySet,
            'remote_secret' => $secretSet ? '********' : null,
            'remote_secret_configured' => $secretSet,
            'remote_bucket' => $s->remote_bucket,
            'remote_region' => $s->remote_region,
            'remote_endpoint' => $s->remote_endpoint,
            'remote_url' => $s->remote_url,
            'remote_use_path_style' => $s->remote_use_path_style,
        ];
    }
}
