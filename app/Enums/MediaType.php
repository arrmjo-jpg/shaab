<?php

declare(strict_types=1);

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Document = 'document';
    case Other = 'other';

    public static function fromMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            in_array($mime, ['application/json', 'text/plain', 'application/pdf'], true) => self::Document,
            default => self::Other,
        };
    }
}
