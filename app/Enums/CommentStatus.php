<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالة الإشراف على التعليق — أساس نظام التعليقات. الإنشاء الافتراضيّ = pending؛
 * انتقالات الإشراف (اعتماد/رفض/سبام) تأتي في شريحة لاحقة.
 */
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
