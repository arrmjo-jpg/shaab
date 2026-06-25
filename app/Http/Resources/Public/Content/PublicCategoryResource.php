<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد تصنيف للقراءة العامة — حقول الواجهة العامة فقط.
 *
 * بدون: parent_id الخام، scope، sort_order، أعلام العرض الإدارية،
 *       translation_group، created_at. children تُحوَّل عبر استدعاء ذاتي.
 */
class PublicCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'locale' => $this->locale,
            // حلٌّ تكراريّ إلى مصفوفة (لا كائن AnonymousResourceCollection): الأكشن
            // يخزّن الناتج في الكاش، وكائن المورد غير المحلول يُسلسَل ويعود عند القراءة
            // كـ __PHP_Incomplete_Class فيكسر الـJSON. مصفوفة محضة تبقى سليمة دائماً.
            'children' => $this->whenLoaded(
                'children',
                fn (): array => self::collection($this->children)->resolve($request),
                [],
            ),
        ];
    }
}
