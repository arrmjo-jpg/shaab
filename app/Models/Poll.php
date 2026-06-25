<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PollAudienceMode;
use App\Enums\PollResultVisibility;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * استطلاع رأي — سؤال واحد + خيارات، مع نافذة جدولة وحالة تفعيل. الأهليّة للتصويت مشتقّة
 * (نشِط + ضمن النافذة). التفعيل لا يُضبط عند الإنشاء/التعديل — يتغيّر عبر نشر مستقلّ.
 */
class Poll extends Model
{
    use AuditsChanges;
    use HasFactory;
    use SoftDeletes;

    protected string $auditLogName = 'poll';

    /** @var array<int,string> */
    protected array $auditAttributes = [
        'question', 'allow_multiple', 'is_active', 'starts_at', 'ends_at',
        'audience_mode', 'result_visibility',
    ];

    protected $fillable = [
        'uuid', 'question', 'allow_multiple', 'is_active', 'starts_at', 'ends_at',
        'audience_mode', 'result_visibility', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_multiple' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'audience_mode' => PollAudienceMode::class,
            'result_visibility' => PollResultVisibility::class,
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $poll): void {
            if (empty($poll->uuid)) {
                $poll->uuid = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ──────────────────────────────────────────────

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** بدأت (أو بلا بداية). */
    public function scopeStarted(Builder $query): Builder
    {
        $now = now();

        return $query->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now));
    }

    /** لم تنتهِ (أو بلا نهاية). */
    public function scopeNotEnded(Builder $query): Builder
    {
        $now = now();

        return $query->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /** مفتوح فعلاً للتصويت: نشِط + ضمن النافذة. */
    public function scopeVotable(Builder $query): Builder
    {
        return $query->active()->started()->notEnded();
    }

    /** أهليّة لحظية للتصويت (مرآة scopeVotable). */
    public function isOpenForVoting(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $now->lessThan($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $now->greaterThan($this->ends_at)) {
            return false;
        }

        return true;
    }

    /** حالة عرض مشتقّة للإدارة (غير مُخزَّنة): inactive | scheduled | closed | open. */
    public function state(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($this->starts_at !== null && $now->lessThan($this->starts_at)) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && $now->greaterThan($this->ends_at)) {
            return 'closed';
        }

        return 'open';
    }

    /** التصويت انتهى نهائياً (مُعطَّل أو تجاوز ends_at) — مقابل «لم يبدأ بعد» (مجدوَل). */
    public function isClosed(): bool
    {
        if (! $this->is_active) {
            return true;
        }

        return $this->ends_at !== null && now()->greaterThan($this->ends_at);
    }

    /** رؤية النتائج لفاعل وفق result_visibility: always | after_vote(صوّت) | after_close(مغلق). */
    public function resultsVisibleTo(bool $hasVoted): bool
    {
        return match ($this->result_visibility) {
            PollResultVisibility::Always => true,
            PollResultVisibility::AfterVote => $hasVoted,
            PollResultVisibility::AfterClose => $this->isClosed(),
        };
    }
}
