<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\CreatePublicCommentAction;
use App\Actions\Public\Content\ListPublicCommentsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Content\StorePublicCommentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * التعليقات العامة (قراءة معتمَدة + إنشاء تعليق/رد). بوّابة العقد (عالميّ ∧ مقال)
 * تُفرَض في الـActions. لا frontend/notifications/likes/edit/real-time/counts هنا.
 */
class CommentController extends Controller
{
    /** قائمة التعليقات المعتمَدة لمقال (قراءة عامة، مبوَّبة). */
    public function index(Request $request, string $locale, string $slug): JsonResponse
    {
        return (new ListPublicCommentsAction)->handle($locale, $slug, $request);
    }

    /** إنشاء تعليق أو رد (parent_id) — يُنشأ pending. */
    public function store(StorePublicCommentRequest $request, string $locale, string $slug): JsonResponse
    {
        return (new CreatePublicCommentAction)->handle($locale, $slug, $request->validated(), $request);
    }
}
