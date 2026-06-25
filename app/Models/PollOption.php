<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * خيار استطلاع. votes_count عدّاد أداء فقط (مصدر الحقيقة = votes / poll_vote_options) —
 * مُستثنى من التدقيق (يتغيّر بالتصويت لا بقصد إداريّ). لا soft delete: حذف صلب عند التحرير،
 * ويُمنع حذف خيار يملك أصواتاً (يُفرَض في UpdatePollAction).
 */
class PollOption extends Model
{
    use AuditsChanges;
    use HasFactory;

    protected string $auditLogName = 'poll_option';

    /** @var array<int,string> votes_count مُستثنى عمداً (عدّاد، لا قصد إداريّ). */
    protected array $auditAttributes = ['poll_id', 'label', 'sort_order'];

    protected $fillable = ['poll_id', 'label', 'sort_order', 'votes_count'];

    protected function casts(): array
    {
        return [
            'poll_id' => 'integer',
            'sort_order' => 'integer',
            'votes_count' => 'integer',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /** الأصوات الموجّهة لهذا الخيار (مصدر الحقيقة). */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVoteOption::class);
    }
}
