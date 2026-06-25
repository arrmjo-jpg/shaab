<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجلّ فحص صحّة بثّ واحد (تاريخ). يُنشأ من MonitorBroadcastHealthAction ويُقلَّم
 * بنافذة احتجاز. checked_at هو زمن الحدث (لا timestamps).
 */
class BroadcastHealthCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'broadcast_id', 'status', 'latency_ms', 'failure_reason', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
