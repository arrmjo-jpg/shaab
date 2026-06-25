<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * إعادة ضبط عدّاد الترقيم التلقائيّ فوق أعلى معرّف مُدرَج (قاعدة #6) — بعد استيراد
 * معرّفات ووردبريس الأصلية (الكبيرة) إلى articles/categories، نرفع العدّاد إلى
 * max(id)+1 كي لا يصطدم المحتوى الجديد (غير المُرحَّل) بالمعرّفات المحجوزة.
 *
 * على MySQL/InnoDB يتقدّم العدّاد عادةً تلقائياً عند إدراج معرّف صريح أعلى، لكن هذا
 * حزام أمان صريح وعابر للقواعد (MySQL/Postgres/SQLite). أفضل-جهد: أيّ تعذّر يُسجَّل
 * ولا يُسقِط ختم التشغيلة. أسماء الجداول حرفية من المُستدعي (لا مدخلات مستخدم).
 */
final class MigrationSequence
{
    /** الجداول المسموح بإعادة ضبطها (حارس صريح ضدّ تمرير اسم جدول عشوائيّ). */
    private const ALLOWED = ['articles', 'categories'];

    public static function realign(string $table): void
    {
        if (! in_array($table, self::ALLOWED, true)) {
            return;
        }

        try {
            $max = (int) DB::table($table)->max('id');
            $driver = DB::connection()->getDriverName();

            match ($driver) {
                'mysql', 'mariadb' => DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = ".($max + 1)),
                'pgsql' => DB::statement(
                    "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), ".max($max, 1).', '.($max > 0 ? 'true' : 'false').')'
                ),
                'sqlite' => self::realignSqlite($table, $max),
                default => null,
            };
        } catch (Throwable $e) {
            Log::warning('wp_migration.sequence_realign_failed', ['table' => $table, 'error' => $e->getMessage()]);
        }
    }

    /** SQLite: عدّاد AUTOINCREMENT محفوظ في sqlite_sequence؛ نضبطه على max فيصير التالي max+1. */
    private static function realignSqlite(string $table, int $max): void
    {
        // الجدول قد لا يملك صفّاً في sqlite_sequence إن لم يُدرَج فيه شيء بعد.
        DB::table('sqlite_sequence')->updateOrInsert(['name' => $table], ['seq' => $max]);
    }
}
