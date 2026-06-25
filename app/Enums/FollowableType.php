<?php

declare(strict_types=1);

namespace App\Enums;

/** نوع الكيان الرياضيّ القابل للمتابعة (معرّفه من 365). */
enum FollowableType: string
{
    case Team = 'team';
    case Competition = 'competition';
    case Player = 'player';
    case Game = 'match'; // «match» كلمة محجوزة كاسم حالة ⇒ الاسم Game والقيمة 'match'.

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
