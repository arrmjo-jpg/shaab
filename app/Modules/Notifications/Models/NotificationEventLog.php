<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سجلّ الأحداث الواردة وقراراتها (تدقيق/تجميع digest/إعادة تشغيل). **غير مُدقَّق** عمداً (حجم عالٍ).
 */
class NotificationEventLog extends Model
{
    protected $table = 'notification_event_log';

    protected $fillable = ['event_key', 'source', 'fingerprint', 'payload', 'decision', 'campaign_id', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
