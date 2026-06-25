<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خيار مُختار ضمن بطاقة تصويت (جدول الربط المُطبَّع). بلا تدقيق صفّيّ. الكتابة في Phase 2.
 */
class PollVoteOption extends Model
{
    use HasFactory;

    protected $table = 'poll_vote_options';

    public $timestamps = false;

    protected $fillable = ['poll_vote_id', 'poll_option_id'];

    protected function casts(): array
    {
        return [
            'poll_vote_id' => 'integer',
            'poll_option_id' => 'integer',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(PollVote::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class);
    }
}
