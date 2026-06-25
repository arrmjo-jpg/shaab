<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Replied = 'replied';
    case Closed = 'closed';

    public function label(): string
    {
        return __('contact.status.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
