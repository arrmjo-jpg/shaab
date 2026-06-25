<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * اشتراك إشعارات البثّ — مصدر الحقيقة لتفضيلات الإشعار (للأهليّة/الجدولة/الحالة):
 *   broadcast_id = null  → عام: «أعلِمني بالبثوث المباشرة».
 *   broadcast_id != null → تذكير حدثٍ بعينه: «ذكّرني بهذا البثّ».
 *
 * وجود الصفّ = مشترِك؛ حذفه = إلغاء. لا تحديثات — صفٌّ ثابت (أُنشئ/حُذف فقط).
 */
class BroadcastNotificationSubscription extends Model
{
    protected $fillable = ['user_id', 'broadcast_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('broadcast_id');
    }

    public function scopeForBroadcast(Builder $query, int $broadcastId): Builder
    {
        return $query->where('broadcast_id', $broadcastId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
