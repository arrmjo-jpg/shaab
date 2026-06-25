<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Enums;

/**
 * حالة قناة واحدة داخل حملة. superseded = حُوِّلت إلى fallback_channel (بنية v1.1 جاهزة،
 * تنفيذ التحويل مؤجّل إلى v1.2). skipped = غير متوفّرة/معطّلة وقت التخطيط (لا تُسقِط الحملة).
 */
enum CampaignChannelStatus: string
{
    case Pending = 'pending';
    case Skipped = 'skipped';
    case Superseded = 'superseded';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
