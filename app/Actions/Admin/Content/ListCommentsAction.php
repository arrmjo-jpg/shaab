<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\CommentStatus;
use App\Http\Resources\Admin\Content\CommentResource;
use App\Models\Comment;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة التعليقات (لوحة الإدارة، قراءة فقط) — مرقّمة، أحدث أولاً، مع فلاتر إشراف
 * اختيارية (status / commentable_type) وبحث في المتن. شريحة الإشراف الأولى؛
 * الاعتماد/الرفض/الحذف يأتي لاحقاً.
 */
class ListCommentsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = Comment::query()
            ->with('user:id,name')
            ->latest();

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, CommentStatus::values(), true)) {
            $query->where('status', $status);
        }

        $type = (string) request()->query('commentable_type', '');
        if ($type !== '') {
            $query->where('commentable_type', $type);
        }

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where('body', 'like', '%'.$search.'%');
        }

        $comments = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: CommentResource::collection($comments)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $comments->total(),
                    'count' => $comments->count(),
                    'per_page' => $comments->perPage(),
                    'current_page' => $comments->currentPage(),
                    'total_pages' => $comments->lastPage(),
                ],
            ],
        );
    }
}
