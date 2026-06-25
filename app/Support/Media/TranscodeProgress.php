<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\MediaProcessingProfile;
use App\Models\MediaAsset;

/**
 * يشتقّ قائمة تحقّق حبيبية لتقدّم ترميز الفيديو من حالة الأصل ومشتقّاته
 * (conversions) المحفوظة تدريجياً عبر TranscodeVideoAssetJob — لا يعيد بناء خط
 * المعالجة، بل يقرأ آثاره فقط.
 *
 * الترتيب = ترتيب الإنتاج الفعلي (metadata → poster → HLS → [reel] مصغّرة →
 * نسخ MP4)، فأوّل خطوة إلزامية مفقودة عند الفشل = «مرحلة الفشل».
 *
 * الحالات لكل قطعة أثرية:
 *   ready    → مُنتَجة ومحفوظة.
 *   pending  → لم تُنتَج بعد (أثناء queued/processing) أو لاحقة لمرحلة الفشل.
 *   failed   → الخطوة الإلزامية التي تعثّرت عندها المعالجة.
 *   skipped  → الأصل جاهز لكن هذه القطعة best-effort لم تُنتَج (مثلاً WebP/دقّة
 *              غير منتَجة لمصدر منخفض) — ليست خطأً.
 */
final class TranscodeProgress
{
    /** سلّم دقّات MP4 (تصاعدي) — مطابق لـ VideoTranscoder. */
    private const LADDER = [360, 480, 720, 1080];

    /** الخطوات الإلزامية لاشتقاق مرحلة الفشل (بترتيب الإنتاج). */
    private const MANDATORY_ORDER = ['metadata', 'poster', 'hls_master', 'hls_segments'];

    /**
     * @return array<string,mixed>|null null لغير الفيديو المرفوع.
     */
    public static function for(MediaAsset $asset): ?array
    {
        if (! $asset->isUploadedVideo()) {
            return null;
        }

        $status = (string) ($asset->processing_status ?? 'queued');
        $isReel = $asset->processing_profile === MediaProcessingProfile::Reel->value;
        $conv = (array) ($asset->conversions ?? []);

        // حضور كل قطعة أثرية من conversions المحفوظة تدريجياً (بترتيب الإنتاج).
        $present = [
            'source' => true,
            'metadata' => $asset->height !== null,
            'poster' => ! empty($conv['poster']['path']),
            'hls_master' => ! empty($conv['hls']['master']),
            'hls_segments' => ! empty($conv['hls']['variants']),
        ];

        if ($isReel) {
            $present['thumbnail_webp'] = ! empty($conv['thumbnail']['webp']);
            foreach (self::expectedHeights($asset->height) as $h) {
                $present['mp4_'.$h] = isset($conv['renditions']['variants'][$h.'p']);
            }
        }

        $failedStage = $status === 'failed' ? self::failedStage($present) : null;

        $artifacts = [];
        $completed = 0;
        foreach ($present as $key => $ok) {
            $state = self::stateFor($key, $ok, $status, $failedStage);
            if ($state === 'ready') {
                $completed++;
            }
            $artifacts[] = [
                'key' => $key,
                'state' => $state,
                'optional' => $key === 'thumbnail_webp' || str_starts_with($key, 'mp4_'),
            ];
        }

        return [
            'status' => $status,
            'profile' => $isReel ? 'reel' : null,
            'total' => count($artifacts),
            'completed' => $completed,
            'failed_stage' => $failedStage,
            // سبب الفشل القابل للتشخيص (Phase 4): undecodable / duration_exceeded /
            // dimensions_exceeded / hls_failed / transcode_error / source_missing.
            'error' => $status === 'failed' ? ($asset->metadata['processing_error'] ?? null) : null,
            'artifacts' => $artifacts,
        ];
    }

    private static function stateFor(string $key, bool $present, string $status, ?string $failedStage): string
    {
        if ($present) {
            return 'ready';
        }
        if ($status === 'failed') {
            return $key === $failedStage ? 'failed' : 'pending';
        }
        if ($status === 'ready') {
            return 'skipped'; // الأصل جاهز وهذه القطعة best-effort لم تُنتَج
        }

        return 'pending'; // queued / processing
    }

    /** أوّل خطوة إلزامية مفقودة = مرحلة الفشل (وإلا «finalize»). */
    private static function failedStage(array $present): string
    {
        foreach (self::MANDATORY_ORDER as $step) {
            if (empty($present[$step])) {
                return $step;
            }
        }

        return 'finalize';
    }

    /**
     * الدقّات المتوقّعة (بلا upscaling) — مطابق لـ VideoTranscoder. السلّم كاملاً
     * إن كان الارتفاع غير معروف بعد.
     *
     * @return array<int,int>
     */
    private static function expectedHeights(?int $sourceHeight): array
    {
        if ($sourceHeight === null || $sourceHeight <= 0) {
            return self::LADDER;
        }

        $selected = array_values(array_filter(
            self::LADDER,
            fn (int $h): bool => $h <= (int) ($sourceHeight * 1.1),
        ));

        return $selected === [] ? [self::LADDER[0]] : $selected;
    }
}
