<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عضوية محادثة + علامة قراءة لكل مستخدم. بيانات تشغيلية عالية التحديث (last_read_at
 * يتغيّر مع كل «تعليم مقروء») → مستثناة من AuditsChanges عمداً (write-amplification).
 */
class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
