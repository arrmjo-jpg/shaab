<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاريخ المسارات القانونية لقوائم التشغيل — يلتقط canonical القديم عند تغيّر
 * slug/locale، يُستهلَك من PlaylistRedirectResolver (المرحلة 5) لإعادة توجيه 301.
 * متطلّب أساسي: تغيّر slug القائمة يُعيد التوجيه تماماً كالفيديو/المقال/الريل.
 */
class PlaylistUrlHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'playlist_url_history';

    protected $fillable = [
        'video_playlist_id', 'locale', 'old_path', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(VideoPlaylist::class, 'video_playlist_id');
    }
}
