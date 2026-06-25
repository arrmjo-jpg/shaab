<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\WriterRequest;
use App\Notifications\ApproveWriterRequestNotification;
use App\Notifications\RejectWriterRequestNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * يُخطر مُقدِّم طلب الكاتب عند قرار القبول/الرفض (P1.4). يُستدعى **post-commit**
 * من أكشنات الموافقة/الرفض. نمط P1.2: best-effort — فشل الإرسال لا يكسر الأكشن
 * (try/catch + Log::warning). الإشعارات ShouldQueue → إرسال البريد لا-متزامن فلا
 * يتأثّر زمن الأكشن. المُستقبِل = مُقدِّم الطلب ($request->user).
 */
final class WriterRequestNotifier
{
    public static function approved(WriterRequest $request): void
    {
        self::send($request, new ApproveWriterRequestNotification);
    }

    public static function rejected(WriterRequest $request): void
    {
        self::send($request, new RejectWriterRequestNotification);
    }

    private static function send(WriterRequest $request, Notification $notification): void
    {
        $user = $request->user;
        if ($user === null) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('writer request notification dispatch failed', [
                'writer_request_id' => $request->id,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
