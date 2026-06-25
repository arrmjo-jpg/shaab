<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Contact;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد رسالة الاتصال (لوحة الإدارة). status/type كقيم (الواجهة تترجمها عبر i18n).
 * is_read مشتقّ من read_at (seen)؛ لا يقود Badge (الـBadge من count(status='new')).
 *
 * @mixin ContactMessage
 */
class ContactMessageResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'type' => $this->type->value,
            'message' => $this->message,
            'status' => $this->status->value,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'reply_body' => $this->reply_body,
            'replied_at' => $this->replied_at?->toISOString(),
            'replied_by' => $this->repliedBy?->name,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
