<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Enums\MediaProcessingProfile;
use App\Enums\MediaVisibility;
use App\Jobs\GenerateMediaAssetConversionsJob;
use App\Jobs\MirrorMediaToRemoteJob;
use App\Jobs\TranscodeVideoAssetJob;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\Media\RemoteStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * رفع أصل إلى المكتبة المركزية المشتركة (P9.2 — قرار B).
 *
 * - dedupe بالـ checksum (SHA-256): الملف نفسه لا يُخزَّن مرّتين؛ يُعاد الأصل الموجود.
 * - يخزّن الأصل على القرص القابل للتبديل (media-library.disk_name، R2).
 * - يستخرج الأبعاد للصور، ويُجدوِل توليد مشتقّات WebP (queue=media).
 *
 * لا HTTP هنا — يُستدعى من المتحكّم (B.1) ومن إعادة توصيل وسائط المقال (B.2).
 */
class StoreMediaAssetAction
{
    public function handle(UploadedFile $file, User $actor, ?string $profile = null, bool $dedupeWithinActor = false): MediaAsset
    {
        $disk = (string) config('media-library.disk_name', 'uploads');
        $checksum = hash_file('sha256', $file->getRealPath());

        // dedupe: نفس المحتوى على نفس القرص ⇒ أعد استخدام الأصل الموجود.
        // dedupeWithinActor (مسار الكاتب): يحصر الـdedupe بأصول الرافع نفسه، فلا يُعاد
        // أصلٌ يملكه مستخدم آخر — وإلا كسر حارس ملكيّة الكاتب (OwnedMediaAsset) عند الربط.
        // الإدارة تمرّ بالقيمة الافتراضيّة (dedupe عالميّ) — سلوكها لا يتغيّر.
        $existing = MediaAsset::query()
            ->where('checksum', $checksum)
            ->where('disk', $disk)
            ->when($dedupeWithinActor, fn ($q) => $q->where('uploaded_by', $actor->id))
            ->first();
        if ($existing !== null) {
            $this->upgradeProfileIfNeeded($existing, $profile);

            return $existing;
        }

        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename = $uuid.($extension !== '' ? '.'.$extension : '');
        $directory = 'assets/'.$uuid;

        $path = $file->storeAs($directory, $filename, ['disk' => $disk]);

        // فشل الكتابة للقرص (مثلاً اعتماديات R2 مرفوضة) يُعيد false مع throw=false —
        // لا نُنشئ أصلاً معطوباً (path فارغ) يعلق في «queued» للأبد؛ نفشل بوضوح.
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException("Media upload to disk [{$disk}] failed (check disk credentials/permissions).");
        }

        [$width, $height] = self::dimensions($file);

        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $isVideo = str_starts_with((string) $mime, 'video/');
        $isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);

        $asset = MediaAsset::create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'width' => $width,
            'height' => $height,
            // كل أصل قابل للمعالجة يدخل دورة الحالة (queued → processing → ready/failed)
            'processing_status' => ($isVideo || $isImage) ? 'queued' : null,
            // ملف المعالجة محايد للمحتوى؛ مفيد للفيديو فقط (نسخ MP4 + WebP).
            'processing_profile' => $isVideo ? $profile : null,
            // حالة مزامنة المرآة الابتدائية (التخزين الهجين).
            'remote_sync_status' => RemoteStorage::enabled() ? 'pending' : 'disabled',
            'visibility' => MediaVisibility::Public->value,
            'uploaded_by' => $actor->id,
        ]);

        // معالجة خارج دورة الطلب (queue=media؛ sync في الاختبارات). نسخ المرآة
        // البعيد يُجدوَل بعد اكتمال المشتقّات (داخل وظيفة المعالجة) لتُنسَخ كلها.
        if ($asset->isConvertibleImage()) {
            GenerateMediaAssetConversionsJob::dispatch($asset->id);
        } elseif ($isVideo) {
            TranscodeVideoAssetJob::dispatch($asset->id);
        } elseif (RemoteStorage::enabled()) {
            // ملف خام (بلا معالجة) — انسخه للبعيد فوراً.
            MirrorMediaToRemoteJob::dispatch($asset->id);
        }

        return $asset->refresh();
    }

    /**
     * إعادة استخدام أصل موجود لأجل ريل: إن كان فيديو مرفوعاً بملف معالجة أدنى،
     * نرقّيه إلى reel ونعيد معالجته عبر الخط القائم — بلا تكرار وسائط ولا خط موازٍ.
     */
    private function upgradeProfileIfNeeded(MediaAsset $asset, ?string $profile): void
    {
        if (
            $profile !== MediaProcessingProfile::Reel->value
            || ! $asset->isUploadedVideo()
            || $asset->processing_profile === MediaProcessingProfile::Reel->value
        ) {
            return;
        }

        $asset->forceFill(['processing_profile' => MediaProcessingProfile::Reel->value])->save();

        // يعيد الحالة إلى queued ويُجدوِل TranscodeVideoAssetJob (نفس الخط).
        (new ReprocessMediaAssetAction)->handle($asset);
    }

    /** @return array{0:?int,1:?int} */
    private static function dimensions(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }
}
