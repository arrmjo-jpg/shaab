<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إحصائات سريعة للأخبار للوحة الإدارة (بطاقات العرض العلوية).
 */
class ArticleStatsAction
{
    public function handle(): JsonResponse
    {
        return ApiResponse::success(data: [
            'total' => Article::query()->count(),
            'published' => Article::query()->where('status', ArticleStatus::Published->value)->count(),
            'draft' => Article::query()->where('status', ArticleStatus::Draft->value)->count(),
            'deleted' => Article::onlyTrashed()->count(),
            // عدّاد «العاجل» أُسقط: is_breaking بلا فهرس → COUNT يفحص الجدول كاملاً
            // (~200ms على 79k صفّ) في كل فتح للصفحة، بلا قيمة تشغيلية. زرّ «مسح العاجل»
            // يبقى يعمل (إجراء منفصل). is_featured مفهرس فيبقى رخيصاً.
            'featured' => Article::query()->where('is_featured', true)->count(),
        ]);
    }
}
