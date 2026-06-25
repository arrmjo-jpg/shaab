<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد الصفحة الثابتة للقراءة العامة — حقول مُعقَّمة فقط (لا حالة إدارية/مؤلّف خام).
 * كل ما يُعاد منشور. content_html مُنقّى مسبقاً (PageContentSanitizer عند الكتابة).
 * أعمدة الـ SEO أصلية؛ الواجهة تبني وسوم <head> منها (نفس نمط بقية الأنواع).
 */
class PublicPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'locale' => $this->locale,
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
            'published_at' => $this->published_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
