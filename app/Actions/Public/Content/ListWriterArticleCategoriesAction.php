<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Enums\ArticleType;
use App\Enums\CategoryScope;
use App\Models\Category;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تصنيفات نموذج الكاتب مفلترةً حسب نوع المحتوى — قائمة مسطّحة (id+name).
 *
 * نفس قاعدة ArticleCategoryGuard المقفولة (مصدر الحقيقة، بلا ازدواج منطق):
 *  - خبر (news)    ⇒ scope ∈ {news, both}
 *  - مقال (opinion) ⇒ scope ∈ {opinion, both}
 *
 * فلا يعرض النموذج إلا الأقسام التي سيقبلها الخادم لهذا النوع.
 */
class ListWriterArticleCategoriesAction
{
    public function handle(string $type, string $locale): JsonResponse
    {
        if (! in_array($locale, Category::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        if (! in_array($type, [ArticleType::News->value, ArticleType::Opinion->value], true)) {
            return ApiResponse::error(__('validation.in', ['attribute' => 'type']), [], 422);
        }

        $required = $type === ArticleType::Opinion->value ? CategoryScope::Opinion : CategoryScope::News;
        $allowed = [$required->value, CategoryScope::Both->value];

        $categories = Category::query()
            ->active()
            ->forLocale($locale)
            ->whereIn('scope', $allowed)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Category $c): array => ['id' => $c->id, 'name' => $c->name])
            ->all();

        return ApiResponse::success(data: $categories);
    }
}
