<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Ad;

use App\Models\AdRequest;
use App\Models\AdRequestNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * مورد طلب الإعلان (الإدارة). status كقيمة (i18n بالواجهة). الملاحظات سجلّ كامل (عند تحميلها).
 *
 * @mixin AdRequest
 */
class AdRequestResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'ad_type' => $this->ad_type,
            'description' => $this->description,
            // المرفق: لا نكشف المسار الخامّ (أمن)؛ التنزيل عبر نقطة الإدارة المحميّة بالـid.
            'has_attachment' => $this->attachment_path !== null,
            'attachment_name' => $this->attachment_name,
            'status' => $this->status->value,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toISOString(),
            'reviewed_by' => $this->reviewedBy?->name,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes
                ->map(fn (AdRequestNote $n): array => [
                    'id' => $n->id,
                    'body' => $n->body,
                    'author' => $n->user?->name,
                    'created_at' => $n->created_at?->toISOString(),
                ])
                ->values()
                ->all()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
