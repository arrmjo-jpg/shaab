<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\EventSource;
use App\Modules\Notifications\Events\NotificationEvent;

/**
 * حاسب هويّة الـdedupe لحملةٍ من حدثها — المصدر الوحيد لمنطق تكرار الحملات. القيمة تُكتب في
 * notification_campaigns.dedupe_hash (UNIQUE) فيتولّى DB منع التكرار ذرّيّاً (لا فحص تطبيقيّ).
 *   manual    ⇒ idempotency_key (جلسة التأليف): يمنع النقر المزدوج، ويسمح بإرسال متعمّد جديد.
 *   recurring ⇒ window (مثل تاريخ digest.daily): يمنع تكرار إعادة تشغيل المُجدوِل.
 *   content   ⇒ معرّف الكيان (article:555): حملة واحدة لكلّ نشر مهما تكرّر الحدث.
 * null ⇒ لا dedupe (يُسمح بتعدّد NULL في MySQL).
 */
final class CampaignDedupe
{
    public static function hash(NotificationEvent $event): ?string
    {
        $scope = self::scope($event);

        return $scope === null ? null : hash('sha256', $event->eventKey.':'.$scope);
    }

    private static function scope(NotificationEvent $event): ?string
    {
        $p = $event->payload;

        return match ($event->source) {
            // اليدويّة: مرساتها draft uuid + انتقال الإرسال الذرّيّ — لا dedupe_hash إلّا توكن صريح (API).
            //          (لا تُدمَج أبداً بمعرّف الكيان: قد يبعث الأدمن نفس المقال لجمهورين عمداً.)
            EventSource::Manual => isset($p['idempotency_key']) ? 'idem:'.$p['idempotency_key'] : null,
            // المجدولة: نافذة (digest:2026-06-19) — تمنع تكرار إعادة تشغيل المُجدوِل.
            EventSource::Scheduled => isset($p['window']) ? 'win:'.$p['window'] : null,
            // النشر التلقائيّ: معرّف الكيان — حملة واحدة لكلّ نشر مهما تكرّر الحدث.
            EventSource::Domain => isset($p['id']) ? 'entity:'.$p['id'] : null,
            // النظام: لا dedupe على مستوى الحملة (تنبيهات direct للأدمن).
            EventSource::System => null,
        };
    }
}
