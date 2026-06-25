<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * يحذف ملفات أصل الوسائط من **الطرفين** (التخزين الهجين): النسخة المحلّية
 * canonical + المرآة البعيدة. مصدر الحقيقة الوحيد لتنظيف الملفات (يُشترَك بين
 * حذف المكتبة وتنظيف الأصول المهجورة).
 *
 * عزل الفشل (fail-safe): كل قرص يُحذَف ضمن try/catch مستقلّ — فشل حذف البعيد
 * يُسجَّل (قابل للاسترجاع يدوياً عبر القرص/المسار) ولا يمنع حذف المحلّي ولا
 * يرمي استثناءً. لا نحذف محلّياً إلا حين يكون قرص الأصل محلّياً (لا فقدان
 * canonical غير متوقّع).
 *
 * الفيديو الخارجي = لا ملفات ⇒ no-op آمن.
 */
final class MediaFileCleaner
{
    /** @var array<int,string> */
    private const LOCAL_DISKS = ['uploads', 'public', 'local'];

    public static function purge(MediaAsset $asset): void
    {
        if ($asset->isExternal()) {
            return;
        }

        $dir = trim(dirname($asset->path), '/.');
        $assetDiskIsLocal = in_array($asset->disk, self::LOCAL_DISKS, true);

        // ── النسخة المحلّية canonical (فقط حين يكون قرص الأصل محلّياً) ──
        if ($assetDiskIsLocal) {
            self::deleteFrom($asset->disk, $dir, $asset->path, 'local');
        }

        // ── المرآة البعيدة ──
        // أصل محلّي primary مع مرآة → قرص المرآة المُعدّ؛ أصل بعيد primary (legacy
        // remote-only) → قرصه نفسه. لا حذف بعيد إن لم تُوجد نسخة بعيدة.
        $remoteDisk = $assetDiskIsLocal
            ? ($asset->stored_remote ? RemoteStorage::diskName() : null)
            : $asset->disk;

        if ($remoteDisk !== null) {
            self::deleteFrom($remoteDisk, $dir, $asset->path, 'remote');
        }
    }

    /** حذف شجرة الأصل من قرص واحد — معزول الفشل. */
    private static function deleteFrom(string $diskName, string $dir, string $path, string $label): void
    {
        try {
            $disk = Storage::disk($diskName);
            if ($dir !== '' && $dir !== '.') {
                $disk->deleteDirectory($dir);
            } else {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            // قابل للاسترجاع: القرص + المسار مُسجَّلان للتنظيف اليدوي/اللاحق.
            Log::warning("MediaFileCleaner: {$label} delete failed", [
                'disk' => $diskName,
                'path' => $dir !== '' ? $dir : $path,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }
}
