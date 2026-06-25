<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * وعي بصحّة التخزين البعيد (S3/R2) — يُخزَّن مؤقتاً (TTL قصير) كي لا يُجرى نداء
 * شبكي عند كل توليد رابط. عند تعذّر الوصول/أي استثناء ⇒ غير سليم (false) فيرتدّ
 * المُحلِّل تلقائياً للتسليم المحلّي.
 *
 * يُستهلَك فقط للأصول ثنائية التخزين (auto)؛ الأصول البعيدة فقط (بلا نسخة محلّية)
 * تُخدَم من البعيد دون فحص صحّة (لا بديل لها).
 */
final class RemoteStorageHealth
{
    private const CACHE_KEY = 'media:remote:healthy';

    private const TTL_SECONDS = 60;

    public static function isHealthy(): bool
    {
        return (bool) Cache::remember(self::CACHE_KEY, self::TTL_SECONDS, static fn (): bool => self::probe());
    }

    /** يُبطل الكاش (يستدعيه فحص الصحّة المجدوَل/إعادة التفعيل لاحقاً). */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function probe(): bool
    {
        try {
            $disk = (string) config('media-library.remote_disk', 'media_remote');
            // فحص خفيف: استعلام وجود مفتاح وهمي (HEAD) — يكشف توفّر القرص/الاعتماديات.
            Storage::disk($disk)->exists('.health-check');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
