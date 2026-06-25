<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Models\Conversation;
use App\Models\User;

/**
 * بوابة الوصول للشات — row-level (عضوية)، لا صلاحيات نظام. مصدر موحّد يمنع تكرار
 * فحص العضوية عبر الـ Actions (helper static خفيف — لا service layer).
 */
final class ChatAccess
{
    public static function isParticipant(Conversation $conversation, User $actor): bool
    {
        return $conversation->hasParticipant($actor->id);
    }
}
