<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عيّنة حضور متزامن للبثّ (لقطة دورية من محرّك الحضور) — يكتبها مُزامن العدّاد
 * (everyMinute) لتمكين الذروة/المتوسّط/منحنى التزامن. سجلّ خفيف بلا timestamps؛
 * نافذة متدحرجة (تقليم retention). الحضور تقريبيّ (B5).
 */
class BroadcastViewerSample extends Model
{
    public $timestamps = false;

    protected $fillable = ['broadcast_id', 'viewers', 'sampled_at'];

    protected function casts(): array
    {
        return [
            'viewers' => 'integer',
            'sampled_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
