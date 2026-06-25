<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تجميعات تحليلات يوميّة لكل عدد (تاريخ × عدد) — تُمكّن تحليلات المدى الزمنيّ في
 * لوحة الإدارة (اليوم/7/30/مدى مخصّص). بلا هوية مستخدم/IP (واعٍ للخصوصية). تُملأ
 * تقدّماً من بيكون الجلسة + وظائف التنزيل (لا تلفيق تاريخيّ).
 */
class EpaperDailyStat extends Model
{
    protected $fillable = [
        'epaper_id', 'stat_date', 'opens', 'sessions', 'total_duration_seconds',
        'pages_viewed', 'searches', 'bookmarks_used', 'resumes_used', 'downloads',
    ];

    protected function casts(): array
    {
        // stat_date يبقى نصّاً ISO 'Y-m-d' (لا cast) — مطابقة firstOrCreate دقيقة عبر
        // القواعد، ومرشّحات المدى تعمل لفظياً (ترتيب ISO = ترتيب زمنيّ).
        return [
            'epaper_id' => 'integer',
            'opens' => 'integer',
            'sessions' => 'integer',
            'total_duration_seconds' => 'integer',
            'pages_viewed' => 'integer',
            'searches' => 'integer',
            'bookmarks_used' => 'integer',
            'resumes_used' => 'integer',
            'downloads' => 'integer',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperDailyStat> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
