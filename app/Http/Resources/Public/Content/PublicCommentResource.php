<?php

declare(strict_types=1);

namespace App\Http\Resources\Public\Content;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد التعليق العام — آمن: لا يكشف email/IP/user_id/status. اسم العرض فقط
 * (مستخدم مُصادَق → الاسم، أو اسم الزائر) + المتن + الوقت + الردود المعتمَدة (مستوى واحد).
 *
 * @mixin Comment
 */
class PublicCommentResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author_name' => $this->user?->name ?? $this->author_name,
            'created_at' => $this->created_at?->toISOString(),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
