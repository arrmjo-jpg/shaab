<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد رسالة الشات — body نصّ صِرف (يُهرَّب عند العرض). المرفق من MediaAsset (CDN).
 * mine: مُحتسَب نسبةً للفاعل الحالي. المرسِل قد يكون null (مستخدم محذوف).
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Tombstone: المحذوفة تبقى في الخيط (حفظ تسلسل الحوار) دون أيّ محتوى أصليّ —
        // لا نصّ/مرفق/مُرسِل/وقت حذف (شات داخليّ بسيط v1).
        if ($this->deleted_at !== null) {
            return [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'conversation_id' => $this->conversation_id,
                'deleted' => true,
                'body' => null,
                'mine' => $this->user_id !== null && $this->user_id === $request->user()?->id,
                'sender' => null,
                'attachment' => null,
                'edited_at' => null,
                'created_at' => $this->created_at?->toISOString(),
            ];
        }

        $mime = (string) ($this->attachment->mime_type ?? '');

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'conversation_id' => $this->conversation_id,
            'deleted' => false,
            'body' => $this->body,
            'mine' => $this->user_id !== null && $this->user_id === $request->user()?->id,
            'sender' => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar,
            ] : null,
            'attachment' => $this->attachment ? [
                'id' => $this->attachment->id,
                'url' => $this->attachment->url(),
                'thumb' => $this->attachment->conversionUrl('thumb'),
                'is_image' => str_starts_with($mime, 'image/'),
                'mime' => $mime,
                'name' => $this->attachment->original_name,
            ] : null,
            'edited_at' => $this->edited_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
