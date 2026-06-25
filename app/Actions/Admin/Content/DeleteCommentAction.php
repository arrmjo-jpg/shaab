<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Article;
use App\Models\Comment;
use App\Support\Frontend\FrontendCacheTags;
use App\Support\Frontend\FrontendRevalidate;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * حذف تعليق — حذف ناعم (SoftDeletes) عبر Eloquent، فيُدقَّق حدث الحذف. الاسترجاع/
 * الحذف النهائيّ خارج نطاق هذه الشريحة.
 */
class DeleteCommentAction
{
    public function handle(Comment $comment): JsonResponse
    {
        $comment->delete();

        // الحذف يغيّر قائمة التعليقات العامّة ⇒ إبطال وسمَي تعليقات المقال (polymorphic).
        if ($comment->commentable_type === (new Article)->getMorphClass()) {
            $slug = Article::withTrashed()->whereKey($comment->commentable_id)->value('slug');
            if (is_string($slug) && $slug !== '') {
                FrontendRevalidate::tags(FrontendCacheTags::comments($slug));
            }
        }

        return ApiResponse::success(message: __('comment.deleted'));
    }
}
