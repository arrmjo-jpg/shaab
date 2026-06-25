<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\ArticleStatus;
use App\Http\Resources\Admin\Content\ArticleResource;
use App\Models\Article;
use App\Models\User;
use App\Support\Cache\ArticleCacheTags;
use App\Support\Content\ArticleCdnPurge;
use App\Support\Content\ArticleRevisionRecorder;
use App\Support\Content\ArticleWorkflowGuard;
use App\Support\Notifications\WriterNotifier;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * انتقال حالة مقال (سير عمل النشر). لا انتقال تلقائي هنا — يدوي محكوم
 * بالأدوار. أتمتة scheduled→published تأتي في موجة لاحقة (P2.1).
 */
class TransitionArticleStatusAction
{
    public function handle(Article $article, array $validated, User $actor): JsonResponse
    {
        $target = ArticleStatus::from($validated['status']);
        $scheduledAt = ! empty($validated['published_at'])
            ? Carbon::parse($validated['published_at'])
            : null;

        if ($denied = ArticleWorkflowGuard::check($actor, $article, $target, $scheduledAt)) {
            return $denied;
        }

        $article = DB::transaction(function () use ($article, $actor, $target, $scheduledAt): Article {
            $article->status = $target->value;

            if ($target === ArticleStatus::Published) {
                $article->published_at = $article->published_at ?? now();
                $article->published_by_id = $actor->id;
            } elseif ($target === ArticleStatus::Scheduled) {
                $article->published_at = $scheduledAt;
            }

            $article->save();

            ArticleRevisionRecorder::snapshot($article, $actor->id);

            return $article;
        });

        // إبطال حبيبي (دخول/خروج المقال من حالة منشور يؤثّر على feed لغته+تفاصيله)
        Cache::tags(ArticleCacheTags::writeTags($article))->flush();
        ArticleCdnPurge::purge($article);

        // إشعار الكاتب (نشر/رفض فقط) — بعد commit وخارج أي transaction (best-effort).
        WriterNotifier::contentStatusChanged($article, 'article', $target->value);

        return ApiResponse::success(
            __('article.status_changed'),
            new ArticleResource(
                $article->fresh()->load(['author:id,name', 'primaryCategory:id,name,slug', 'categories:id,name,slug'])
            )
        );
    }
}
