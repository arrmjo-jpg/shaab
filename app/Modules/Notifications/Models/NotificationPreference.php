<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تفضيل إشعار لكلّ (مستخدم × نطاق × قناة) — إلغاء اشتراك صريح (opted_in=false). مُدقَّق (قرار
 * مستخدم منخفض الحجم، يطابق تدقيق Follow). يُرشّح topics الجهاز + مستلمي الحملات/Direct لاحقاً.
 */
class NotificationPreference extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'notification_preference';

    /** @var array<int,string> */
    protected array $auditAttributes = ['user_id', 'scope_type', 'scope_key', 'channel', 'opted_in'];

    protected $fillable = ['user_id', 'scope_type', 'scope_key', 'channel', 'opted_in'];

    protected function casts(): array
    {
        return [
            'opted_in' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
