<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EngagementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * سجلّ فاعل واحد: من تفاعل (مستخدم/زائر) بأي نوع على أي هدف. يفرض «تفاعل واحد»
 * ومنع التكرار عبر قيد فرادة (target + type + actor_key).
 */
class Engagement extends Model
{
    protected $fillable = [
        'engageable_type', 'engageable_id', 'user_id', 'fingerprint', 'actor_key', 'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => EngagementType::class,
        ];
    }

    public function engageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
