<?php

declare(strict_types=1);

namespace App\Support\Cache;

/**
 * ثوابت مدة الكاش (بالثواني) — يمنع الأرقام السحرية المتناثرة.
 *
 * القيم تطابق config/performance.php → cache.* (المصدر القابل للضبط عبر env).
 * استخدم هذه الثوابت في الكود؛ استخدم config('performance.cache.*')
 * عند الحاجة لقيمة قابلة للضبط بيئياً.
 *
 *   REALTIME → بيانات حسّاسة للتفاعل (الرائج/مقاييس الريلز) — تتغيّر لحظياً
 *   SHORT    → قوائم متغيّرة (مستخدمون، مقالات)
 *   MEDIUM   → بيانات متوسطة التغيّر
 *   LONG     → أدوار/صلاحيات/تصنيفات (تتغيّر بمعدّل منخفض)
 *   SETTINGS → إعدادات النظام (نادراً ما تتغيّر)
 */
final class CacheTtl
{
    // نافذة قصيرة للنقاط الحسّاسة للتفاعل: تُبقي الترتيب/المقاييس طازجة دون
    // تفريغ كاش عند كل حدث تفاعل (لا عواصف flush) — موازنة الطزاجة بالكفاءة.
    public const REALTIME = 60;   // دقيقة

    public const SHORT = 300;    // 5 دقائق

    public const MEDIUM = 1800;   // 30 دقيقة

    public const LONG = 21600;  // 6 ساعات

    public const SETTINGS = 86400;  // 24 ساعة

    // ─── أسماء دلالية حسب نوع البيانات ────────────────────────────────
    public const LISTS = self::SHORT;

    public const METADATA = self::LONG;
}
