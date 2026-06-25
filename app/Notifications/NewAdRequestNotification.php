<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * إشعار الإدارة بوصول طلب إعلان جديد. database+mail، ShouldQueue، post-commit عبر
 * AdminAdRequestNotifier. **ليس مصدر Badge** — Badge من count(status='new').
 */
class NewAdRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $adRequestId,
        public readonly string $company,
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
            'kind' => 'ad_request',
            'event' => 'created',
            'id' => $this->adRequestId,
            'company' => $this->company,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('ad_request.notification.subject'))
            ->line(__('ad_request.notification.line', ['company' => $this->company]));
    }
}
