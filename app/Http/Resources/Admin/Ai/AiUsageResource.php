<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Ai;

use App\Models\AiUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * صفّ استخدام ذكاء اصطناعي للعرض الإداري — لا محتوى حسّاس. يكشف من/أي مزوّد/أي
 * عملية/مصدر الناتج/التوكِنات المقدّرة/التكلفة المقدّرة/الوقت فقط.
 *
 * @mixin AiUsage
 */
class AiUsageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'user_id' => $this->user_id,
            'provider' => $this->provider,
            'action' => $this->action,
            'source' => $this->source,
            'tokens' => $this->tokens,
            'estimated_cost' => (float) $this->estimated_cost,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
