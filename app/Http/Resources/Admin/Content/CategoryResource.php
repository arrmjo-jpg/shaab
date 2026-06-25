<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد التصنيف — مخرَج deterministic (لا نماذج خام).
 * children تُضمَّن فقط عند تحميلها (بناء الشجرة في الـ Action).
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'locale' => $this->locale,
            'translation_group' => $this->translation_group,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'scope' => $this->scope->value,
            'status' => $this->status->value,
            'show_in_header' => $this->show_in_header,
            'show_in_body' => $this->show_in_body,
            'show_in_footer' => $this->show_in_footer,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
