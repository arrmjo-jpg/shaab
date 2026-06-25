<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار لمُقدِّم طلب الكاتب عند القبول (P1.4). قناتان: database (يظهر في جرس
 * الكاتب بعد ترقيته إلى كاتب) + mail. **ShouldQueue** — غير حاجب: زمن approve لا
 * يتأثّر بسرعة SMTP. يُرسَل post-commit عبر WriterRequestNotifier (best-effort).
 */
class ApproveWriterRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int,string>
     */
    public function via(object $notifiable): array
    {
        // database فقط إن وُجد الجدول (حارس متانة)؛ mail دائماً.
        return Schema::hasTable('notifications') ? ['database', 'mail'] : ['mail'];
    }

    /**
     * حمولة قناة database (تُقرأ في NotificationResource عبر lang).
     *
     * @return array<string,string>
     */
    public function toArray(object $notifiable): array
    {
        return ['kind' => 'writer_request', 'event' => 'approved'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notification.writer_request_mail.approved.subject'))
            ->greeting(__('notification.writer_request_mail.greeting', ['name' => $notifiable->name]))
            ->line(__('notification.writer_request_mail.approved.line1'))
            ->line(__('notification.writer_request_mail.approved.line2'));
    }
}
