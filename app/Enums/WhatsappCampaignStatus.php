<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة حملة واتساب:
 *   draft → (إرسال فوري أو جدولة) scheduled → sending → completed|failed
 *   (draft|scheduled) → cancelled
 */
enum WhatsappCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
