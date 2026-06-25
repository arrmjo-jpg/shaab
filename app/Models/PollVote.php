<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بطاقة تصويت ثابتة — مصدر الحقيقة الموثوق للأصوات (votes_count عدّاد أداء قابل لإعادة
 * البناء منها). بلا تدقيق صفّيّ (حقيقة عالية الحجم، مرآة AdCounter/AdStatDaily). الكتابة
 * تأتي في Phase 2 (التصويت العام). فرادة (poll_id, voter_hash) تمنع التكرار.
 */
class PollVote extends Model
{
    use HasFactory;

    protected $table = 'poll_votes';

    public $timestamps = false;

    protected $fillable = ['poll_id', 'voter_hash', 'created_at'];

    protected function casts(): array
    {
        return [
            'poll_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(PollVoteOption::class);
    }
}
