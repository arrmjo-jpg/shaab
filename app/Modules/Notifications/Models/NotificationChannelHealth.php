<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Modules\Notifications\Enums\ChannelHealthState;
use App\Modules\Notifications\Enums\ChannelKey;
use Illuminate\Database\Eloquent\Model;

/**
 * صفّ صحّة قناة في السجلّ المركزيّ. **غير مُدقَّق** عمداً: يُحدَّث كلّ probe (كلّ 10د) + كلّ فشل
 * إرسال ⇒ تدقيقه ضوضاء. channel هو المفتاح الطبيعيّ. effective_state يُعرَض في الإدارة وحده.
 */
class NotificationChannelHealth extends Model
{
    protected $table = 'notification_channel_health';

    protected $fillable = [
        'channel', 'effective_state', 'configured', 'healthy',
        'last_checked_at', 'last_ok_at', 'last_error', 'latency_ms', 'consecutive_failures',
    ];

    protected function casts(): array
    {
        return [
            'channel' => ChannelKey::class,
            'effective_state' => ChannelHealthState::class,
            'configured' => 'boolean',
            'healthy' => 'boolean',
            'last_checked_at' => 'datetime',
            'last_ok_at' => 'datetime',
            'latency_ms' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }
}
