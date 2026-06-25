<?php

declare(strict_types=1);

namespace App\Enums;

enum AdRequestStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Negotiating = 'negotiating';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Closed = 'closed';

    public function label(): string
    {
        return __('ad_request.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
