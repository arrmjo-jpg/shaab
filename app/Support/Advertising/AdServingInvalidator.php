<?php

declare(strict_types=1);

namespace App\Support\Advertising;

use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPlacement;
use App\Models\AdZone;
use App\Support\Cache\AdCacheTags;
use Illuminate\Support\Facades\Cache;

/**
 * إبطال صريح لبِرَك خدمة الإعلانات (لا observers — سياسة AlphaCMS: الإبطال في الـ Action).
 * تفريغ وسم المساحة يُسقط كل تنويعات اللغة/الجهاز لتلك المساحة فتُعاد بناؤها عند الطلب
 * التالي مع الحالة الجديدة. يُستدعى عند أي تغيّر يمسّ الأهليّة (حالة الحملة/الإسناد…).
 */
final class AdServingInvalidator
{
    /** يُبطل المساحات التي تظهر فيها إبداعات الحملة. */
    public static function forCampaign(AdCampaign $campaign): void
    {
        $creativeIds = AdCreative::query()->where('ad_campaign_id', $campaign->id)->select('id');
        $zoneIds = AdPlacement::query()->whereIn('ad_creative_id', $creativeIds)->select('ad_zone_id');
        $zoneKeys = AdZone::query()->whereIn('id', $zoneIds)->pluck('key');

        self::flushZones($zoneKeys->all());
    }

    /** يُبطل المساحات التي يظهر فيها هذا الإبداع (عبر إسناداته). */
    public static function forCreative(AdCreative $creative): void
    {
        $zoneIds = AdPlacement::query()->where('ad_creative_id', $creative->id)->select('ad_zone_id');
        $zoneKeys = AdZone::query()->whereIn('id', $zoneIds)->pluck('key');

        self::flushZones($zoneKeys->all());
    }

    /** @param  array<int,string>  $zoneKeys */
    public static function flushZones(array $zoneKeys): void
    {
        foreach (array_values(array_unique($zoneKeys)) as $key) {
            Cache::tags([AdCacheTags::zone((string) $key)])->flush();
        }
    }
}
