<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\CommentResource;
use App\Models\Article;
use App\Models\Comment;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * إشراف على تعليق — ضبط حالة الإشراف (approved/rejected/spam) عبر Eloquent save،
 * فيُدقَّق تلقائياً (status ضمن auditAttributes). الحالة مُتحقَّق منها في الطلب.
 */
class ModerateCommentAction
{
    public function handle(Comment $comment, string $status): JsonResponse
    {
        $comment->status = $status; // EnumCast يحوّل القيمة المتحقَّقة
        $comment->save();

        // الاعتماد/الرفض يغيّر قائمة التعليقات العامّة ⇒ إبطال وسمَي تعليقات المقال (polymorphic).
        if ($comment->commentable_type === (new Article)->getMorphClass()) {
            $slug = Article::withTrashed()->whereKey($comment->commentable_id)->value('slug');
            if (is_string($slug) && $slug !== '') {
                FrontendRevalidate::tags(FrontendCacheTags::comments($slug));
            }
        }

        return ApiResponse::success(
            message: __('comment.moderated.'.$status),
            data: new CommentResource($comment->loadMissing('user')),
        );
    }
}
