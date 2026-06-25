<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Tags\Tag;

/**
 * مورد الوسم (لوحة الإدارة) — يكشف الاسم/الـslug بكل اللغات (للتحرير ثنائي اللغة)
 * مع عدّاد الاستخدام (يُحقَن من الاستعلام/الـAction، صفر إن غاب).
 *
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'type' => $this->type,
            'usage_count' => (int) ($this->usage_count ?? 0),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
