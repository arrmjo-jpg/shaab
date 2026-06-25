<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\NewContactMessageNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * يُخطر مستخدمي الإدارة (أصحاب صلاحية contact-messages.view) بوصول رسالة اتصال جديدة.
 * يُستدعى **post-commit** من CreateContactMessageAction. نمط WriterRequestNotifier:
 * best-effort (try/catch + Log::warning) — فشل الإشعار لا يكسر الإنشاء. يُعيد استخدام
 * نظام الإشعارات الحاليّ (database+mail)؛ **ليس مصدر عدّاد Badge**.
 */
final class AdminContactNotifier
{
    public static function created(ContactMessage $message): void
    {
        try {
            $recipients = User::permission('contact-messages.view')->get();
            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new NewContactMessageNotification(
                $message->id,
                $message->name,
                $message->type->value,
            ));
        } catch (\Throwable $e) {
            Log::warning('contact message notification dispatch failed', [
                'contact_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
