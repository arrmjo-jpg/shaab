<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Settings\GeneralSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * بريد تحقّق للإداريين (عربي بالكامل) — يحوي رابطاً موقّعاً مؤقتاً
 * يؤكّد البريد عبر مسار backend ثم يعيد التوجيه للوحة الإدارة.
 */
class VerifyAdminEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'admin.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForPasswordReset()),
            ]
        );

        $app = config('app.name');
        try {
            $site = app(GeneralSettings::class)->site_name;
            if ($site !== '') {
                $app = $site;
            }
        } catch (\Throwable) {
            // إعدادات غير متاحة — أبقِ الاسم الافتراضي
        }

        return (new MailMessage)
            ->subject(__('auth.verify_email.subject'))
            ->greeting(__('auth.verify_email.greeting'))
            ->line(__('auth.verify_email.line1'))
            ->action(__('auth.verify_email.action'), $url)
            ->line(__('auth.verify_email.expire', ['count' => 60]))
            ->line(__('auth.verify_email.line2'))
            ->salutation(__('auth.reset_email.salutation', ['app' => $app]));
    }
}
