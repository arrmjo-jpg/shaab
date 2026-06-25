<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\DeleteCommentAction;
use App\Actions\Admin\Content\ListCommentsAction;
use App\Actions\Admin\Content\ModerateCommentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ModerateCommentRequest;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    /** قائمة التعليقات للإشراف (قراءة فقط) — comments.view. */
    public function index(): JsonResponse
    {
        return (new ListCommentsAction)->handle();
    }

    /** إشراف: اعتماد/رفض/سبام — comments.approve. */
    public function updateStatus(ModerateCommentRequest $request, Comment $comment): JsonResponse
    {
        return (new ModerateCommentAction)->handle($comment, $request->validated()['status']);
    }

    /** حذف ناعم — comments.delete. */
    public function destroy(Comment $comment): JsonResponse
    {
        return (new DeleteCommentAction)->handle($comment);
    }
}
