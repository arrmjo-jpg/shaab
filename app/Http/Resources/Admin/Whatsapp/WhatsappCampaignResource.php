<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Whatsapp;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * سجلّ الحملة — يشمل كل ما طلبه سجلّ الحملات: الاسم/النوع/الحالة/التوقيت/عدد المستلمين/
 * الناجح/الفاشل + المجموعات. (أسباب فشل الرسائل في WhatsappCampaignMessageResource.)
 *
 * @property WhatsappCampaign $resource
 */
class WhatsappCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'type' => $this->resource->type->value,
            'status' => $this->resource->status->value,
            'message_text' => $this->resource->message_text,
            'media_type' => $this->resource->media_type->value,
            'media_asset_id' => $this->resource->media_asset_id,
            'article_id' => $this->resource->article_id,
            'recipients_total' => $this->resource->recipients_total,
            'sent_count' => $this->resource->sent_count,
            'failed_count' => $this->resource->failed_count,
            'scheduled_at' => $this->resource->scheduled_at?->toIso8601String(),
            'started_at' => $this->resource->started_at?->toIso8601String(),
            'finished_at' => $this->resource->finished_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'groups' => $this->resource->relationLoaded('groups')
                ? $this->resource->groups->map(fn (WhatsappGroup $g): array => ['id' => $g->id, 'name' => $g->name])->all()
                : [],
        ];
    }
}
