<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد المستخدم العام — يُستخدم في نقطة /me.
 * لا يتضمن الـ token.
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $latestRequest = $this->writerRequests()->latest('id')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'is_writer' => (bool) $this->is_writer,
            // رقم الهاتف (E.164) + اختيار الاشتراك في حملات واتساب — null في phone ⇒ تُعرض نافذة الجمع.
            'phone' => $this->phone,
            'whatsapp_subscribed' => (bool) $this->whatsapp_subscribed,
            // الصورة من Spatie media فقط (D3) — null عند الغياب.
            'avatar' => $this->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'bio' => $this->bio,
            'social_links' => (object) ($this->social_links ?? []),
            // آخر طلب ترقية (لتفرّع لوحة المستخدم). سبب الرفض (decision_note) يُضاف في شريحته.
            'writer_request' => $latestRequest === null ? null : [
                'status' => $latestRequest->status->value,
                // created_at = تاريخ تقديم الطلب (لحدث "تقديم طلب الكاتب" في Activity Feed) — حقل
                // قراءة فقط، بلا migration/منطق. سبب الرفض (decision_note) يُضاف في شريحته.
                'created_at' => $latestRequest->created_at->toISOString(),
                'reviewed_at' => $latestRequest->reviewed_at?->toISOString(),
            ],
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
