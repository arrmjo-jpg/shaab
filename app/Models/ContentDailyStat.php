<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * صفّ تجميع يوميّ للتفاعل لكل هدف محتوى (polymorphic) — بُعد زمني فوق
 * engagement_counters. يكتبه DailyEngagementRollup؛ تقرؤه تحليلات الكيان (نطاق زمني).
 * تيليمتري إلى-الأمام فقط: لا أثر رجعيّ قبل أوّل كتابة.
 */
class ContentDailyStat extends Model
{
    protected $table = 'content_daily_stats';

    protected $fillable = [
        'engageable_type', 'engageable_id', 'day',
        'views', 'likes', 'dislikes', 'favorites',
        'views_direct', 'views_internal', 'views_search', 'views_social', 'views_referral',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'views' => 'integer',
            'likes' => 'integer',
            'dislikes' => 'integer',
            'favorites' => 'integer',
            'views_direct' => 'integer',
            'views_internal' => 'integer',
            'views_search' => 'integer',
            'views_social' => 'integer',
            'views_referral' => 'integer',
        ];
    }

    public function engageable(): MorphTo
    {
        return $this->morphTo();
    }
}
