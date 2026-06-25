<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * الكاتب القانوني الوحيد لكل المحتوى المُرحَّل: «كتاب الموقع» (غير قابل للتفاوض).
 *
 * لا تُرحَّل حسابات/كتّاب/ضيوف ووردبريس إطلاقاً — كل المقالات تُسنَد لهذا الكاتب
 * كملكية قانونية مؤقّتة، والتنظيف التحريري (إنشاء/إعادة إسناد كتّاب حقيقيين) يدوي لاحق.
 *
 * فحص صارم (قاعدة #8): التنفيذ يستدعي resolve()/id() ويفشل سريعاً إن كان الكاتب
 * غائباً (لا إنشاء تلقائي). الإنشاء عبر ensure() في خطوة إعداد صريحة فقط.
 */
final class MigrationAuthor
{
    /** البريد الحارس لمنع التكرار عند الإنشاء (لا يُستخدم للدخول). */
    private const SENTINEL_EMAIL = 'kottab-almawqi@alphacms.local';

    /** الاسم القانوني المعروض للكاتب. */
    public static function name(): string
    {
        return (string) config('wp-migration.author_name', 'كتاب الموقع');
    }

    /** هل الكاتب القانوني موجود (للفحص المسبق قبل التنفيذ). */
    public static function exists(): bool
    {
        return User::query()->where('name', self::name())->exists();
    }

    /**
     * يُرجِع الكاتب القانوني، أو يرمي إن كان غائباً — فحص صارم (قاعدة #8).
     * لا إنشاء تلقائي أثناء التنفيذ: التنفيذ على كاتب غائب يفشل سريعاً.
     */
    public static function resolve(): User
    {
        $user = User::query()->where('name', self::name())->first();
        if ($user === null) {
            throw new RuntimeException('wp_migration: canonical author "'.self::name().'" is missing.');
        }

        return $user;
    }

    public static function id(): int
    {
        return self::resolve()->id;
    }

    /**
     * إنشاء/ضمان الكاتب القانوني — خطوة إعداد صريحة فقط (ليست جزءاً من التنفيذ).
     * بلا أدوار فلا وصول للوحة — مجرّد كيان إسناد. idempotent.
     */
    public static function ensure(): User
    {
        $existing = User::query()->where('name', self::name())->first();
        if ($existing !== null) {
            return $existing;
        }

        return User::create([
            'name' => self::name(),
            'email' => self::SENTINEL_EMAIL,
            // الـ cast 'hashed' يُجزّئ القيمة — نمرّر نصاً عشوائياً.
            'password' => Str::random(40),
            'status' => UserStatus::Active->value,
            'is_writer' => true,
            'email_verified_at' => now(),
        ]);
    }
}
