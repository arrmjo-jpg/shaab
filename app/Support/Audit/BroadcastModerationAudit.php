<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\Broadcast;
use App\Models\User;

/**
 * تدقيق صريح لإجراءات الإشراف على جمهور البثّ (B6) — مرآة RbacAudit. الإجراءات تقع
 * على طبقة الحضور (Redis/الكاش) لا على سمات النموذج، فأحداث Eloquent لا تلتقطها؛
 * نسجّلها يدوياً بأثرٍ دائم في سجلّ النشاط: الفاعل (causer) + البثّ (subject) +
 * الحدث + الخصائص (العضو/السبب/المدّة/الانتهاء). سجلّ النشاط هو الأثر الدائم؛
 * الإنفاذ نفسه مؤقّت في الكاش (TTL).
 *
 * مساعد ساكن بلا حالة — يُستدعى مباشرةً من إجراءات الإشراف.
 */
final class BroadcastModerationAudit
{
    /**
     * @param  array<string,mixed>  $properties
     */
    public static function log(string $event, ?User $actor, Broadcast $broadcast, array $properties = []): void
    {
        activity('broadcast')
            ->causedBy($actor)
            ->performedOn($broadcast)
            ->event($event)
            ->withProperties(array_merge(
                array_filter($properties, static fn ($v): bool => $v !== null),
                ['timestamp' => now()->toISOString()],
            ))
            ->log(__('audit.broadcast.'.$event));
    }
}
