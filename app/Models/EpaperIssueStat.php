<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عدّادات تحليلات القارئ المجمَّعة لكل عدد (Phase 5) — بلا هوية مستخدم/IP.
 */
class EpaperIssueStat extends Model
{
    protected $fillable = [
        'epaper_id', 'opens', 'sessions', 'total_duration_seconds',
        'pages_viewed', 'searches', 'bookmarks_used', 'resumes_used', 'downloads', 'last_activity_at',
    ];

    protected function casts(): array
    {
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
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Epaper, EpaperIssueStat> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
