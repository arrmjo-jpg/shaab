<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationType;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * محادثة شات داخليّة بين المدراء. ثلاثة أنواع موحّدة البنية (general/direct/group).
 * يُدقَّق (دورة حياة المحادثة قليلة التردّد وذات قيمة) — بخلاف الرسائل (تيّار أحداث).
 */
class Conversation extends Model
{
    use AuditsChanges;
    use SoftDeletes;

    protected string $auditLogName = 'conversation';

    /** @var array<int,string> */
    protected array $auditAttributes = ['type', 'title', 'created_by'];

    protected $fillable = [
        'uuid', 'type', 'title', 'dm_key', 'created_by', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $c): void {
            if (empty($c->uuid)) {
                $c->uuid = (string) Str::uuid();
            }
        });
    }

    /** مفتاح حتميّ لفرادة المحادثة المباشرة بين مُعرّفَي مستخدمَين. */
    public static function dmKey(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** آخر رسالة (لمعاينة القائمة) — eager-load لتفادي N+1. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Access (row-level — لا صلاحيات نظام) ────────────────────────

    /** هل المستخدم عضو في هذه المحادثة؟ (بوابة الوصول الوحيدة). */
    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    /** المحادثات التي يكون المستخدم عضواً فيها. */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $userId));
    }
}
