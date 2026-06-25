<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد بروفيل الكاتب العام — **حقول آمنة للنشر فقط** (لا بريد/حالة/أدوار/تسجيل دخول/أسرار).
 * يُعاد فقط لمستخدم is_writer نشِط (البوّابة في ShowPublicWriterAction). الصورة من Spatie media.
 *
 * @mixin \App\Models\User
 */
class PublicWriterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'bio' => $this->bio,
            'social_links' => (object) ($this->social_links ?? []),
        ];
    }
}
