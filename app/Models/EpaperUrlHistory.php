<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مسار قديم لعدد رقميّ (تحويل عند تغيّر الـ slug) — يحافظ على الروابط/SEO.
 */
class EpaperUrlHistory extends Model
{
    protected $table = 'epaper_url_history';

    protected $fillable = [
        'epaper_id',
        'locale',
        'old_path',
        'reason',
    ];

    /** @return BelongsTo<Epaper, EpaperUrlHistory> */
    public function epaper(): BelongsTo
    {
        return $this->belongsTo(Epaper::class, 'epaper_id');
    }
}
