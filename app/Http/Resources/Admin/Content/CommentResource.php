<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Content;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد التعليق (لوحة الإدارة) — المتن + الحالة + هوية الكاتب (مستخدم أو زائر) +
 * هدف التعليق + ربط الرد. للقراءة في شريحة الإشراف الأولى. يفترض تحميل علاقة user.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'status' => $this->status->value,
            'parent_id' => $this->parent_id,
            'commentable_type' => $this->commentable_type,
            'commentable_id' => $this->commentable_id,
            'author' => [
                'user_id' => $this->user_id,
                'name' => $this->user?->name ?? $this->author_name,
                'is_guest' => $this->user_id === null,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
