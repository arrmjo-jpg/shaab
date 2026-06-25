<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactMessageType: string
{
    case Inquiry = 'inquiry';
    case Complaint = 'complaint';
    case Suggestion = 'suggestion';
    case Other = 'other';

    public function label(): string
    {
        return __('contact.type.'.$this->value);
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
