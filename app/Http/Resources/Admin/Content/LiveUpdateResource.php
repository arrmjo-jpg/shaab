<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use App\Support\Content\ArticleMediaPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تحديث التغطية الحيّة (لوحة الإدارة) — مخرَج deterministic.
 * content_json مصدر المحرّر؛ content_html عرض مشتقّ مُعقَّم.
 * الوسائط تُشكَّل بنفس مُقدّم وسائط المقال (article_media المشترك).
 *
 * قرار معماري (Increment 2, Part 6) — سيو التحديثات الفردية:
 *   الخيار (A) المعتمَد: التحديثات أجزاء من الخط الزمني وليست كياناتٍ مفهرسة
 *   مستقلّة. الكيان المفهرس صاحب الـ SEO هو الحدث المباشر (المقال) نفسه عبر
 *   PublicSeoBuilder. لا نضيف seo_title/description/canonical لكل تحديث (تجنّب
 *   فرط البناء وتشتيت محرّكات البحث). الربط العميق يتم عبر مرساة #update-{id}.
 */
class LiveUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'title' => $this->title,
            'content_json' => $this->content_json,
            'content_html' => $this->content,
            'is_pinned' => $this->is_pinned,
            'is_breaking' => $this->is_breaking,
            'is_featured' => $this->is_featured,
            'happened_at' => $this->happened_at?->toISOString(),
            'author' => $this->whenLoaded('author', fn (): array => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
            ]),
            'media' => $this->whenLoaded('mediaAssets', fn (): array => ArticleMediaPresenter::admin($this->resource)),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
