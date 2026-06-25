<?php

declare(strict_types=1);

namespace App\Support\Vertix;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * الكاتب القانونيّ المُسنَد لكلّ محتوى Vertix المُرحَّل (مستقلّ عن كاتب WordPress).
 * يُنشأ مرّة بـ firstOrCreate على بريد حارس؛ بلا أدوار (كيان إسناد فقط). Idempotent.
 */
final class VertixAuthor
{
    private const SENTINEL_EMAIL = 'vertix-import@alphacms.local';

    public static function name(): string
    {
        return (string) config('vertix.author_name', 'القلعة نيوز');
    }

    public static function resolve(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::SENTINEL_EMAIL],
            [
                'name' => self::name(),
                'password' => Str::random(40), // cast 'hashed' يُجزّئها
                'status' => UserStatus::Active->value,
                'is_writer' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    public static function id(): int
    {
        return self::resolve()->id;
    }
}
