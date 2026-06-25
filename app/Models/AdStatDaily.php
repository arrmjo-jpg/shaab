<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تجميع يوميّ لأداء الإسناد (placement) + أبعاد تقارير مشتقّة مُزالة-التطبيع
 * (حملة/إبداع/مساحة) تبقى للتاريخ بعد حذف الإسناد. يُغذّى من AdStatsRollup.
 */
class AdStatDaily extends Model
{
    protected $table = 'ad_stats_daily';

    protected $fillable = [
        'ad_placement_id', 'ad_zone_id', 'ad_creative_id', 'ad_campaign_id', 'day',
        'impressions', 'clicks',
        'impressions_direct', 'impressions_internal', 'impressions_search',
        'impressions_social', 'impressions_referral',
    ];

    protected function casts(): array
    {
        return [
            'ad_placement_id' => 'integer',
            'ad_zone_id' => 'integer',
            'ad_creative_id' => 'integer',
            'ad_campaign_id' => 'integer',
            'day' => 'date:Y-m-d',
            'impressions' => 'integer',
            'clicks' => 'integer',
        ];
    }
}
