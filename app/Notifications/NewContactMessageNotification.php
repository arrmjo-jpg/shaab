<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار الإدارة بوصول رسالة «اتصل بنا» جديدة. قناتان: database (جرس الإدارة) + mail.
 * **ShouldQueue** غير حاجب (زمن الإرسال العامّ لا يتأثّر بـSMTP). يُرسَل post-commit عبر
 * AdminContactNotifier (best-effort). **ليس مصدر عدّاد Badge** — Badge من count(status='new').
 */
class NewContactMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $contactMessageId,
        public readonly string $senderName,
        public readonly string $type,
    ) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return Schema::hasTable('notifications') ? ['database', 'mail'] : ['mail'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contact_message',
            'event' => 'created',
            'id' => $this->contactMessageId,
            'type' => $this->type,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('contact.notification.subject'))
            ->line(__('contact.notification.line', [
                'type' => __('contact.type.'.$this->type),
                'name' => $this->senderName,
            ]));
    }
}
