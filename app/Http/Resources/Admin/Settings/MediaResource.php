<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد أصل وسائط منمَّط. الأصول الخاصة لا تكشف رابطاً.
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'visibility' => $this->visibility->value,
            'url' => $this->url(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
