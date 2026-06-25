<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

/**
 * مُحلِّل تسليم الوسائط الهجين — يقرّر قرص الخدمة (محلّي canonical أو مرآة بعيدة)
 * لكل أصل، ثم يولّد الرابط عبره. القرار **لكل أصل** (لا لكل ملف) كي تُخدَم كل
 * ملفات الأصل من أصل واحد — شرط سلامة HLS (المسارات النسبية في m3u8).
 *
 * منطق القرار (يطابق متطلّبات المرحلة):
 *  - أصل بعيد فقط (stored_remote && !stored_local) → بعيد دائماً (لا بديل) —
 *    يُبقي الوسائط القديمة على R2 تعمل بصرف النظر عن المفتاح/الصحّة.
 *  - توجد نسخة محلّية:
 *      • المرآة معطّلة، أو غير سليمة، أو لا نسخة بعيدة، أو preferred_delivery=local
 *        → محلّي (canonical الافتراضي).
 *      • مُفعَّلة + سليمة + متزامنة → بعيد.
 *  - مفاتيح الكائنات متطابقة على القرصين (assets/{uuid}/…) فلا تحويل مسار.
 */
final class MediaDeliveryResolver
{
    /** @var array<int,string> أقراص تُعتبر محلّية canonical. */
    private const LOCAL_DISKS = ['uploads', 'public', 'local'];

    /** الرابط العام للمسار المعطى عبر القرص المُختار لهذا الأصل. */
    public static function url(MediaAsset $asset, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Storage::disk(self::diskNameFor($asset))->url($path);
    }

    /** اسم القرص الذي يُخدَم منه هذا الأصل (قرار موحّد لكل ملفاته). */
    public static function diskNameFor(MediaAsset $asset): string
    {
        return self::shouldServeRemote($asset)
            ? self::remoteDisk($asset)
            : self::localDisk($asset);
    }

    private static function shouldServeRemote(MediaAsset $asset): bool
    {
        // بعيد فقط — لا نسخة محلّية ⇒ يُخدَم من البعيد دائماً (لا بديل، يتجاوز المفتاح/الصحّة).
        if ($asset->stored_remote && ! $asset->stored_local) {
            return true;
        }

        // من هنا توجد نسخة محلّية → المفتاح/الصحّة قد يُرجعان للمحلّي (canonical).
        if (! $asset->stored_remote) {
            return false;
        }
        if ($asset->preferred_delivery === 'local') {
            return false;
        }
        if (! self::remoteEnabled()) {
            return false;
        }
        if (! RemoteStorageHealth::isHealthy()) {
            return false;
        }

        return true; // مُفعَّل + سليم + متزامن (auto أو remote)
    }

    private static function remoteEnabled(): bool
    {
        return RemoteStorage::enabled();
    }

    private static function localDisk(MediaAsset $asset): string
    {
        return in_array($asset->disk, self::LOCAL_DISKS, true)
            ? $asset->disk
            : (string) config('media-library.canonical_disk', 'uploads');
    }

    private static function remoteDisk(MediaAsset $asset): string
    {
        // أصل محلّي primary مع مرآة → قرص المرآة المُعدّ؛ أصل بعيد primary (s3) → قرصه.
        return in_array($asset->disk, self::LOCAL_DISKS, true)
            ? (string) config('media-library.remote_disk', 'media_remote')
            : $asset->disk;
    }
}
