<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WriterRequestStatus;
use App\Support\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterRequest extends Model
{
    use AuditsChanges;

    protected string $auditLogName = 'writer_request';

    /** @var array<int,string> */
    protected array $auditAttributes = ['user_id', 'status', 'note', 'reviewed_by', 'reviewed_at'];

    protected $fillable = [
        'user_id',
        'status',
        'note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WriterRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
