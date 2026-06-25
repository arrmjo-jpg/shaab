<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Models\User;
use App\Modules\Notifications\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جهاز push مُسجَّل. **غير مُدقَّق** (لا AuditsChanges) عمداً: last_seen_at/fcm_token يتغيّران
 * بكثرة (تدوير + كلّ تسجيل) فتدقيقه يُغرِق السجلّ — يطابق استثناء WhatsappCampaignMessage.
 * device_id هو المفتاح الفريد (upsert عند التدوير). user_id=null ⇒ جهاز ضيف.
 */
class MobileDevice extends Model
{
    protected $fillable = [
        'user_id', 'device_id', 'platform', 'fcm_token', 'locale', 'is_active', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
