<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد الصفحة الثابتة (لوحة الإدارة) — مخرَج deterministic، لا نماذج خام.
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'locale' => $this->locale,
            'translation_group' => $this->translation_group,
            'title' => $this->title,
            'slug' => $this->slug,
            'content_html' => $this->content,
            'template' => $this->template,
            'show_in_header' => $this->show_in_header,
            'show_in_footer' => $this->show_in_footer,
            'sort_order' => $this->sort_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'keywords' => $this->seo_keywords,
                'canonical_url' => $this->canonical_url,
                'robots' => $this->robots,
            ],
            'canonical_path' => $this->canonicalPath(),
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'published_at' => $this->published_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
