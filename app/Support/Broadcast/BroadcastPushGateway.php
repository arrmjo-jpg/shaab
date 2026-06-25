<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use Illuminate\Support\Facades\Log;

/**
 * بوّابة دفع البثّ — حدّ التسليم الخارجي (FCM topics). نموذج المواضيع: نشر رسالة
 * واحدة لموضوع يتولّى المزوّد توزيعها على آلاف الأجهزة المشتركة — لا حلقة على
 * المستخدمين هنا إطلاقاً (يتحمّل 100k+ بنشرٍ واحد لكل موضوع).
 *
 * Firebase Messaging غير مُهيّأ بعد (المنصّة تستخدم تخزين Firebase فقط)، فالتنفيذ
 * الافتراضي يسجّل النشر (stub أمين — لا ادّعاء تسليم وهميّ). عند تهيئة FCM يُستبدَل
 * جسم publish() بنداء FCM HTTP v1 لموضوع (أو يُربط تنفيذ بديل في الحاوية) دون أي تغيير
 * في المستدعين. تُحَلّ من الحاوية فتكون قابلة للـ mock في الاختبارات.
 */
class BroadcastPushGateway
{
    /** @param  array<string,mixed>  $payload */
    public function publish(string $topic, array $payload): void
    {
        if (! (bool) config('broadcast.notifications.enabled', true)) {
            return;
        }

        // TODO(FCM): استبدل بنداء FCM HTTP v1 (message.topic) عند تهيئة Firebase Messaging.
        Log::info('broadcast.notification.publish', ['topic' => $topic, 'payload' => $payload]);
    }
}
