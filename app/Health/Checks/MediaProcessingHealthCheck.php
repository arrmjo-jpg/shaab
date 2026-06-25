<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Models\MediaAsset;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * مراقبة صحّة خط معالجة الوسائط (Phase 5 — تشخيص).
 *
 * - عالقة في processing أطول من العتبة ⇒ فشل صحّي (عامل الطابور معطّل أو مهمة
 *   معلّقة) — أخطر إشارة، تُنبِّه المشغّل فوراً.
 * - فشل ترميز كثير خلال 24 ساعة ⇒ تحذير صحّي (مشكلة متكرّرة تستحق النظر).
 *
 * يُعرَض عبر نقطة /system/health المحمية ويُشغَّل ضمن health:check المجدوَل،
 * فتنطلق إشعارات الفشل (mail/slack) المضبوطة في config/health.php.
 */
class MediaProcessingHealthCheck extends Check
{
    public function run(): Result
    {
        $stuckMinutes = (int) config('performance.media.stuck_processing_minutes', 60);
        $failThreshold = (int) config('performance.media.failed_alert_threshold', 10);

        $stuck = MediaAsset::query()
            ->where('processing_status', 'processing')
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->count();

        $failed = MediaAsset::query()
            ->where('processing_status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        $result = Result::make()
            ->meta(['stuck_processing' => $stuck, 'failed_24h' => $failed])
            ->shortSummary("{$failed} failed / {$stuck} stuck");

        if ($stuck > 0) {
            return $result->failed(
                "{$stuck} media asset(s) stuck in processing for >{$stuckMinutes}m — the media queue worker may be down."
            );
        }

        if ($failed >= $failThreshold) {
            return $result->warning("{$failed} media transcode(s) failed in the last 24h.");
        }

        return $result->ok('Media processing healthy.');
    }
}
