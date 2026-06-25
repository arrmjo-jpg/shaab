<?php

declare(strict_types=1);

namespace App\Support\Vertix;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * اتصال قاعدة مصدر Vertix (قراءة فقط منطقياً) — مستقلّ تماماً عن WordPress.
 * الاسم من config('vertix.connection'); يُحقَن في الاختبارات عبر fake().
 */
final class VertixConnection
{
    private static ?Connection $fake = null;

    public static function name(): string
    {
        return (string) config('vertix.connection', 'vertix');
    }

    public static function db(): Connection
    {
        return self::$fake ?? DB::connection(self::name());
    }

    public static function canConnect(): bool
    {
        try {
            self::db()->select('select 1 as ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** حقن اتصال مزيَّف للاختبارات فقط. */
    public static function fake(Connection $connection): void
    {
        self::$fake = $connection;
    }

    public static function forget(): void
    {
        self::$fake = null;
    }
}
