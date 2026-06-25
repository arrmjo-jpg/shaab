<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\ArticleType;
use App\Enums\CategoryScope;
use App\Models\Category;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حارس إسناد التصنيفات للمقال — نموذج موحّد (اختيار متعدّد، بلا تمييز رئيسي).
 *
 * كل الأنواع (news/live/opinion): قسم واحد على الأقل، أي عدد بلا حدّ.
 * كل قسم يجب أن يطابق نطاق النوع (news|both للأخبار/التغطية، opinion|both للرأي)
 * ولغة المقال. لا قاعدة «الأعمق» ولا حدّ أقصى ولا قيد قسم واحد للرأي.
 *
 * ملاحظة: «الرئيسي» مفهوم داخلي للعرض العام فقط (أوّل قسم مُختار) — لا يفرضه
 * المحرّر. يبقى primaryCategoryId مُمرَّراً للتوافق مع طبقة الموقع العام/الـ SEO.
 *
 * يُرجع JsonResponse عند الرفض، أو null إذا كان الإسناد سليماً.
 */
final class ArticleCategoryGuard
{
    /**
     * @param  array<int,int>  $secondaryIds  بقية الأقسام المُختارة (عدا الأوّل)
     */
    public static function check(
        ArticleType $type,
        string $articleLocale,
        int $primaryCategoryId,
        array $secondaryIds
    ): ?JsonResponse {
        $requiredScope = $type === ArticleType::Opinion
            ? CategoryScope::Opinion
            : CategoryScope::News; // news + live

        $primary = Category::query()->find($primaryCategoryId);
        if ($primary === null) {
            return ApiResponse::error(__('article.primary_category_not_found'), [], 422);
        }

        if ($primary->locale !== $articleLocale) {
            return ApiResponse::error(__('article.primary_category_locale_mismatch'), [], 422);
        }

        if (! $primary->scope->allowsArticleScope($requiredScope)) {
            return ApiResponse::error(__('article.category_scope_mismatch'), [], 422);
        }

        $secondaryIds = array_values(array_unique(array_map('intval', $secondaryIds)));

        if (in_array($primaryCategoryId, $secondaryIds, true)) {
            return ApiResponse::error(__('article.primary_in_secondary'), [], 422);
        }

        if ($secondaryIds === []) {
            return null;
        }

        $secondaries = Category::query()->whereKey($secondaryIds)->get();
        if ($secondaries->count() !== count($secondaryIds)) {
            return ApiResponse::error(__('article.secondary_category_not_found'), [], 422);
        }

        foreach ($secondaries as $secondary) {
            if ($secondary->locale !== $articleLocale) {
                return ApiResponse::error(__('article.secondary_category_locale_mismatch'), [], 422);
            }

            if (! $secondary->scope->allowsArticleScope($requiredScope)) {
                return ApiResponse::error(__('article.category_scope_mismatch'), [], 422);
            }
        }

        return null;
    }
}
