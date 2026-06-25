<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Enums\AdCampaignStatus;
use App\Models\AdCampaign;
use Carbon\CarbonInterface;

/**
 * آلة حالة دورة حياة الحملة — مصدر الحقيقة الوحيد للانتقالات (لا سلوك ضمنيّ مخفيّ).
 *
 * الانتقالات اليدوية (admin):
 *   draft      → scheduled | archived
 *   scheduled  → active | draft | archived
 *   active     → paused | completed | archived
 *   paused     → active | completed | archived
 *   completed  → paused | archived
 *   archived   → draft | paused
 *
 * الانتقالات التلقائية (scheduler — ads:campaigns-tick):
 *   scheduled  → completed   (now > ends_at — نافذة فائتة)
 *   scheduled  → active       (now ≥ starts_at و now ≤ ends_at)
 *   active     → completed    (now > ends_at)
 *   paused/completed/archived/draft: لا تُدار آلياً (تحكّم إداريّ بحت).
 *
 * حارس النافذة: التفعيل اليدوي (→active) يتطلّب نافذة غير منتهية.
 */
final class AdCampaignLifecycle
{
    /** @return array<int,AdCampaignStatus> */
    public static function manualTargets(AdCampaignStatus $from): array
    {
        return match ($from) {
            AdCampaignStatus::Draft => [AdCampaignStatus::Scheduled, AdCampaignStatus::Archived],
            AdCampaignStatus::Scheduled => [AdCampaignStatus::Active, AdCampaignStatus::Draft, AdCampaignStatus::Archived],
            AdCampaignStatus::Active => [AdCampaignStatus::Paused, AdCampaignStatus::Completed, AdCampaignStatus::Archived],
            AdCampaignStatus::Paused => [AdCampaignStatus::Active, AdCampaignStatus::Completed, AdCampaignStatus::Archived],
            AdCampaignStatus::Completed => [AdCampaignStatus::Paused, AdCampaignStatus::Archived],
            AdCampaignStatus::Archived => [AdCampaignStatus::Draft, AdCampaignStatus::Paused],
        };
    }

    public static function canTransitionManually(AdCampaignStatus $from, AdCampaignStatus $to): bool
    {
        return in_array($to, self::manualTargets($from), true);
    }

    /** التفعيل اليدوي مسموح فقط ضمن النافذة (ends_at غير منتهٍ). */
    public static function canActivateNow(AdCampaign $campaign, ?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $campaign->ends_at === null || $now->lessThanOrEqualTo($campaign->ends_at);
    }

    /** الانتقال التلقائي المستحقّ لحملة عند لحظة معيّنة، أو null إن لا شيء. */
    public static function autoTransitionFor(AdCampaign $campaign, ?CarbonInterface $now = null): ?AdCampaignStatus
    {
        $now ??= now();
        $start = $campaign->starts_at;
        $end = $campaign->ends_at;

        return match ($campaign->status) {
            AdCampaignStatus::Scheduled => match (true) {
                $end !== null && $now->greaterThan($end) => AdCampaignStatus::Completed,
                ($start === null || $now->greaterThanOrEqualTo($start))
                    && ($end === null || $now->lessThanOrEqualTo($end)) => AdCampaignStatus::Active,
                default => null,
            },
            AdCampaignStatus::Active => $end !== null && $now->greaterThan($end)
                ? AdCampaignStatus::Completed
                : null,
            default => null,
        };
    }
}
