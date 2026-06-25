<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار لمُقدِّم طلب الكاتب عند الرفض (P1.4). mail فقط — المرفوض يبقى غير كاتب فلا
 * يصل لجرس الكاتب (لا تخفيف بوّابة /notifications). **ShouldQueue** — غير حاجب.
 * يُرسَل post-commit عبر WriterRequestNotifier (best-effort).
 */
class RejectWriterRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification.writer_request_mail.rejected.subject'))
            ->greeting(__('notification.writer_request_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('notification.writer_request_mail.rejected.line1'))
            ->line(__('notification.writer_request_mail.rejected.line2'));
    }
}
