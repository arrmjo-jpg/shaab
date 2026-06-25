<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Settings\MediaStorageSettings;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

/**
 * مساعد التخزين البعيد — مصدر موحّد لحالة التفعيل واسم قرص المرآة.
 * fail-safe: أي تعذّر في قراءة الإعدادات ⇒ معطّل (يرتدّ النظام للمحلّي).
 */
final class RemoteStorage
{
    public static function enabled(): bool
    {
        try {
            return (bool) app(MediaStorageSettings::class)->remote_enabled;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function diskName(): string
    {
        return (string) config('media-library.remote_disk', 'media_remote');
    }

    /**
     * يبني قرص المرآة (media_remote) ديناميكياً من الإعدادات الحالية — idempotent.
     *
     * يُستدعى وقت boot (لطلبات الويب) وعند بداية وظائف المرآة/السحب (للـ queue
     * worker الطويل العمر الذي بُنيت تهيئته مرّة واحدة وقت إقلاعه). هكذا تفعيل/
     * تغيير الإعدادات من اللوحة يسري فوراً بلا إعادة تشغيل يدوي للـ worker.
     *
     * fail-safe: اعتماديات ناقصة ⇒ لا يُبنى القرص (يرتدّ المُحلِّل للمحلّي).
     * لا يُمرَّر visibility (R2 لا يدعم ACL)؛ throw=false كي لا يرمي عند الفشل.
     */
    public static function configureDisk(): void
    {
        try {
            $s = app(MediaStorageSettings::class);
            $name = self::diskName();

            // غير مُهيّأ (مفتاح/باكِت ناقص) ⇒ لا تبنِ القرص (يرتدّ للمحلّي). لا
            // نَمسح القرص هنا كي لا نُبطِل Storage::fake في الاختبارات.
            if ($s->remote_driver !== 's3' || $s->remote_key === '' || $s->remote_bucket === '') {
                return;
            }

            Config::set('filesystems.disks.'.$name, [
                'driver' => 's3',
                'key' => $s->remote_key,
                'secret' => $s->remote_secret,
                'region' => $s->remote_region !== '' ? $s->remote_region : 'auto',
                'bucket' => $s->remote_bucket,
                'endpoint' => $s->remote_endpoint !== '' ? $s->remote_endpoint : null,
                'url' => $s->remote_url !== '' ? $s->remote_url : null,
                'use_path_style_endpoint' => $s->remote_use_path_style,
                'throw' => false,
            ]);

            // أبطِل أي نسخة مُحلّلة سابقاً (تهيئة قديمة) كي يُعاد بناؤها بالإعداد الحالي.
            Storage::forgetDisk($name);
        } catch (\Throwable) {
            // إعدادات غير متاحة (قبل الترحيل مثلاً) — تجاهل بأمان.
        }
    }
}
