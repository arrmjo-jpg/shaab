<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Events\NotificationEvent;

/**
 * بصمة محتوى الحدث — لأغراض المراقبة فقط (debugging / كشف تكرار / observability). تُسجَّل في
 * notification_event_log.fingerprint (مُفهرَس، **غير فريد**) فلا تمنع تنفيذاً. نفس
 * (key + source + payload) ⇒ نفس البصمة ⇒ كشف «أُطلق مرّتين» عبر GROUP BY. مستقلّة عن
 * dedupe_hash (منع، فريد، على الحملات) — هويّتان لطبقتين مختلفتين.
 */
final class EventFingerprint
{
    public static function for(NotificationEvent $event): string
    {
        return hash('sha256', $event->eventKey.'|'.$event->source->value.'|'.self::canonical($event->payload));
    }

    /** @param array<string,mixed> $payload */
    private static function canonical(array $payload): string
    {
        ksort($payload); // ترتيب مفاتيح مستقرّ ⇒ بصمة حتميّة بصرف النظر عن ترتيب الإدخال

        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
