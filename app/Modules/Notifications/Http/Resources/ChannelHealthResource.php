<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Modules\Notifications\Models\NotificationChannelHealth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationChannelHealth
 */
final class ChannelHealthResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->channel->value,
            'effective_state' => $this->effective_state?->value,
            'sendable' => $this->effective_state?->isSendable() ?? false,
            'configured' => (bool) $this->configured,
            'healthy' => (bool) $this->healthy,
            'last_checked_at' => $this->last_checked_at,
            'last_ok_at' => $this->last_ok_at,
            'last_error' => $this->last_error,
            'latency_ms' => $this->latency_ms,
            'consecutive_failures' => $this->consecutive_failures,
        ];
    }
}
