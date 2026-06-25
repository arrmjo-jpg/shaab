<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Resources;

use App\Settings\NotificationSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationSettings
 */
final class NotificationSettingsResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        /** @var NotificationSettings $s */
        $s = $this->resource;

        return [
            'enabled' => $s->enabled,
            'critical_bypass' => $s->critical_bypass,
            'quiet_hours_enabled' => $s->quiet_hours_enabled,
            'quiet_hours_start' => $s->quiet_hours_start,
            'quiet_hours_end' => $s->quiet_hours_end,
            'quiet_hours_timezone' => $s->quiet_hours_timezone,
        ];
    }
}
