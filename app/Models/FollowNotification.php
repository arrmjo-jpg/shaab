<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفتر منع تكرار إشعارات «تابع» (صفّ لكلّ مستخدم+إشعار مُرسَل). **عمدًا بلا AuditsChanges**: سجلّ آليّ يكتبه
 * مُرسِل الإشعارات (لا فعل مستخدم/مدير ذو تاريخ تغيير دلاليّ) — تدقيقه ضجيج بلا فائدة (استثناء موثَّق كـ
 * SportFixture، راجع model-audit). الكتابة عبر firstOrCreate + قيد unique(user_id,dedup_key) = منع تكرار محكم.
 */
class FollowNotification extends Model
{
    protected $fillable = ['user_id', 'game_id', 'kind', 'event_id', 'dedup_key', 'sent_at'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'game_id' => 'integer',
            'event_id' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
