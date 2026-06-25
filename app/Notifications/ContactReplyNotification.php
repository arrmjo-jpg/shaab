<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ردّ الإدارة على رسالة «اتصل بنا» — يُرسَل لبريد المُرسِل (on-demand، غير مرتبط بمستخدم).
 * **غير ShouldQueue عمداً**: الإرسال متزامن ليُعرَف نجاحه/فشله، فلا تتحوّل الحالة إلى
 * replied إلا بعد نجاح فعليّ (قرار التصميم المعتمد).
 */
class ContactReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $subject,
        public readonly string $body,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('contact.reply_mail.subject', ['subject' => $this->subject]))
            ->line($this->body);
    }
}
