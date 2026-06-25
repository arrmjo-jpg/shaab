<?php

declare(strict_types=1);

namespace App\Support\Video;

use App\Actions\Admin\Media\StoreExternalVideoAction;
use App\Models\MediaAsset;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;

/**
 * ملكية الوسائط الأساسية للفيديو — القرار المعماري الصريح:
 *
 *   • كل فيديو يملك مصدراً واحداً قابلاً للتشغيل عبر media_asset_id (1 ↔ 1).
 *   • فيديو **مرفوع** (source_type=uploaded): الأصل **مملوك حصرياً** للفيديو
 *     (مُرفوع من أجله، 1:1 مخصّص) — يُنظَّف عند الحذف النهائي إن لم يُشارَك.
 *   • فيديو **خارجي** (youtube/vimeo/direct_mp4): الأصل **مرجع مكتبة مُشترَك**
 *     (StoreExternalVideoAction يزيل التكرار حسب provider_id/embed_url) — لا
 *     يُحذَف أبداً عند حذف فيديو، فقد يشير إليه فيديو/مقال/ريل آخر.
 *
 * هذا الفصل يمنع حذف أصل مُشترَك بالخطأ، ويضمن تنظيف الأصول المرفوعة المخصّصة فقط.
 */
final class VideoMedia
{
    /**
     * يربط مصدراً خارجياً (يُتحقَّق ويُحَلّ ويُزال تكراره) كأصل مكتبة مُشترَك.
     * يُحدّث media_asset_id + source_type المُزال-التطبيع. يُعيد false إن تعذّر.
     */
    public static function attachExternalSource(Video $video, string $url, User $actor): bool
    {
        $spec = VideoSourceResolver::classify($url);
        if ($spec === null) {
            return false;
        }

        $asset = app(StoreExternalVideoAction::class)->handle($url, $actor);

        $video->media_asset_id = $asset->id;
        $video->source_type = $spec['source_type'];
        $video->save();

        return true;
    }

    /**
     * يربط أصل فيديو مرفوعاً (مملوكاً 1:1 — رُفع لهذا الفيديو). يرفض غير الفيديو
     * المرفوع (خارجي/صورة). يُحدّث source_type=uploaded.
     */
    public static function attachUploadedAsset(Video $video, MediaAsset $asset): bool
    {
        if (! $asset->isUploadedVideo()) {
            return false;
        }

        $video->media_asset_id = $asset->id;
        $video->source_type = 'uploaded';
        $video->save();

        return true;
    }

    /**
     * تنظيف الأصل عند الحذف النهائي للفيديو — يحذف الأصل **المرفوع المملوك** فقط،
     * وبشرط ألّا يكون مُشاركاً من أي فيديو/مقال/ريل آخر. الأصول الخارجية المُشتركة
     * لا تُحذَف إطلاقاً (مرجع مكتبة). حذف MediaAsset يُشغّل التنظيف المزدوج
     * (محلّي + مرآة بعيدة) عبر مراقب الوسائط القائم.
     */
    public static function releaseOwnedAsset(Video $video): void
    {
        $asset = $video->relationLoaded('mediaAsset') ? $video->mediaAsset : $video->mediaAsset()->first();
        if ($asset === null) {
            return;
        }

        // الأصول الخارجية مراجع مكتبة مُشتركة (dedupe) — لا تُحذَف مع الفيديو.
        if ($asset->isExternal()) {
            return;
        }

        if (self::isReferencedElsewhere($asset, $video)) {
            return;
        }

        $asset->delete();
    }

    /** هل الأصل مُشار إليه من فيديو/مقال/ريل آخر (دفاعي ضدّ حذف أصل مُشترَك)؟ */
    private static function isReferencedElsewhere(MediaAsset $asset, Video $video): bool
    {
        $byOtherVideos = Video::query()
            ->where('media_asset_id', $asset->id)
            ->where('id', '!=', $video->id)
            ->exists();

        $byReels = Reel::query()->where('media_asset_id', $asset->id)->exists();

        $byArticles = $asset->articles()->exists();

        return $byOtherVideos || $byReels || $byArticles;
    }
}
