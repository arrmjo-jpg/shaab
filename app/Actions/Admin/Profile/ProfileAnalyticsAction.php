<?php

declare(strict_types=1);

namespace App\Actions\Admin\Profile;

use App\Enums\ArticleStatus;
use App\Enums\ReelStatus;
use App\Models\AiUsage;
use App\Models\Article;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/**
 * مقاييس العمل التشغيلية للمستخدم — أرقام حقيقية مُجمَّعة من قاعدة البيانات فقط
 * (لا بيانات وهمية). المحتوى يُنسَب عبر author_id/uploaded_by، واستخدام الذكاء
 * الاصطناعي عبر user_id (النداءات الفعلية source=ai). مشاهدات المقالات من عمود
 * views_count (الريلز لا تملك عموداً مكافئاً، فلا تُعرَض مشاهداتها — تجنّب التلفيق).
 */
class ProfileAnalyticsAction
{
    public function handle(User $user): JsonResponse
    {
        $id = $user->getKey();

        $articles = [
            'created' => Article::where('author_id', $id)->count(),
            'published' => Article::where('author_id', $id)
                ->where('status', ArticleStatus::Published->value)->count(),
            'drafts' => Article::where('author_id', $id)
                ->where('status', ArticleStatus::Draft->value)->count(),
            'views_generated' => (int) Article::where('author_id', $id)->sum('views_count'),
        ];

        $reels = [
            'created' => Reel::where('author_id', $id)->count(),
            'published' => Reel::where('author_id', $id)
                ->where('status', ReelStatus::Published->value)->count(),
            'drafts' => Reel::where('author_id', $id)
                ->where('status', ReelStatus::Draft->value)->count(),
        ];

        $media = [
            'uploads' => MediaAsset::where('uploaded_by', $id)->count(),
        ];

        // استخدام الذكاء الاصطناعي — النداءات الفعلية فقط (source=ai)
        $aiRow = Schema::hasTable('ai_usages')
            ? AiUsage::where('user_id', $id)->where('source', 'ai')
                ->selectRaw('COUNT(*) as requests, COALESCE(SUM(tokens),0) as tokens, COALESCE(SUM(estimated_cost),0) as cost')
                ->first()
            : null;

        $ai = [
            'requests' => (int) ($aiRow->requests ?? 0),
            'tokens' => (int) ($aiRow->tokens ?? 0),
            'estimated_cost' => round((float) ($aiRow->cost ?? 0), 6),
        ];

        return ApiResponse::success(data: [
            'articles' => $articles,
            'reels' => $reels,
            'media' => $media,
            'ai' => $ai,
        ]);
    }
}
