<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية للفيديو — يلتقط canonical القديم عند تغيّر slug/locale،
 * يُستهلَك من VideoRedirectResolver (المرحلة 5) لإعادة توجيه 301. مرآة ReelUrlHistory.
 */
class VideoUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'video_url_history';

    protected $fillable = [
        'video_id', 'locale', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
