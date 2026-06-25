<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * رسالة شات — تيّار أحداث عالي التردّد، **مستثناة من AuditsChanges عمداً** (قرار
 * معماريّ: تدقيق كل رسالة = write-amplification بلا قيمة تحليلية؛ نمط Slack/Discord).
 * body نصّ صِرف فقط (لا HTML — يُهرَّب عند العرض). المرفق عبر MediaAsset.
 */
class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'conversation_id', 'user_id', 'body', 'attachment_asset_id', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            if (empty($m->uuid)) {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** المرسِل (قد يكون null إن حُذف المستخدم — تُحفَظ الرسالة كتاريخ). */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'attachment_asset_id');
    }
}
