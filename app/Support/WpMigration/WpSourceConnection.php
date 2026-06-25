<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Models\MigrationRun;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * يُسجِّل اتصال قاعدة بيانات مصدر ووردبريس ديناميكياً (وقت التشغيل) من إعدادات
 * التشغيلة — بيانات الاعتماد تُدخَل عبر الواجهة ولا تُوضَع في .env. الاستخدام
 * للقراءة فقط منطقياً (القرّاء يستعملون باني الاستعلام لا كتابات). بادئة جداول
 * ووردبريس (مثل 3b5qs_) تُطبَّق على مستوى الاتصال فيقرأ ->table('posts').
 */
final class WpSourceConnection
{
    /**
     * اتصال مزيَّف للاختبارات فقط (يُحقَن sqlite بدل mysql الحيّ). null في الإنتاج —
     * لا أثر إطلاقاً ما لم يستدعِ اختبارٌ fake(). نظير Http::fake/Storage::fake.
     */
    private static ?Connection $fake = null;

    /** اسم الاتصال الديناميكي. */
    public static function name(): string
    {
        return (string) config('wp-migration.connection', 'wp_source');
    }

    /**
     * يبني مصفوفة إعدادات الاتصال من تشغيلة (كلمة المرور تُفكّ تلقائياً عبر cast).
     *
     * @return array<string,mixed>
     */
    public static function config(MigrationRun $run): array
    {
        return [
            'driver' => 'mysql',
            'host' => (string) $run->db_host,
            'port' => $run->db_port ?: 3306,
            'database' => (string) $run->db_name,
            'username' => (string) $run->db_username,
            'password' => (string) $run->db_password,
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            // بادئة جداول ووردبريس تُطبَّق هنا (3b5qs_posts ← ->table('posts')).
            'prefix' => (string) ($run->table_prefix ?? ''),
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => [
                PDO::ATTR_TIMEOUT => 10,
            ],
        ];
    }

    /**
     * يُسجِّل الاتصال ويُعيد اسمه (للاستخدام عبر DB::connection($name)).
     * يُنظّف أي اتصال سابق بنفس الاسم لضمان إعدادات طازجة.
     */
    public static function configure(MigrationRun $run): string
    {
        $name = self::name();

        config(['database.connections.'.$name => self::config($run)]);
        DB::purge($name);

        return $name;
    }

    public static function connection(MigrationRun $run): Connection
    {
        return self::$fake ?? DB::connection(self::configure($run));
    }

    /** حقن اتصال مزيَّف للاختبارات فقط (لا يُستدعى من كود الإنتاج). */
    public static function fake(Connection $connection): void
    {
        self::$fake = $connection;
    }

    /** يفصل وينظّف اتصال المصدر بعد الانتهاء (ويمسح أي تزييف اختبار). */
    public static function forget(): void
    {
        self::$fake = null;
        DB::purge(self::name());
    }
}
