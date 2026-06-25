<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Whatsapp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** رسالة ضمن حملة — للسجلّ التفصيليّ: الرقم/الحالة/سبب الفشل عند توفّره. */
class WhatsappCampaignMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'phone' => $this->resource->phone,
            'status' => $this->resource->status->value,
            'error' => $this->resource->error,
            'sent_at' => $this->resource->sent_at?->toIso8601String(),
        ];
    }
}
