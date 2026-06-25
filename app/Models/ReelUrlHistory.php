<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية للريل — التقاط canonical القديم عند تغيّر slug/locale،
 * يُستهلَك من ReelRedirectResolver لإعادة توجيه 301. مرآة ArticleUrlHistory.
 */
class ReelUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'reel_url_history';

    protected $fillable = [
        'reel_id', 'locale', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }
}
