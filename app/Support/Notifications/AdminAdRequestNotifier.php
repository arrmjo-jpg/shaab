<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\AdRequest;
use App\Models\User;
use App\Notifications\NewAdRequestNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * يُخطر مستخدمي الإدارة (أصحاب ad-requests.view) بطلب إعلان جديد. post-commit، best-effort
 * (try/catch + Log::warning). يُعيد استخدام نظام الإشعارات الحاليّ. ليس مصدر Badge.
 */
final class AdminAdRequestNotifier
{
    public static function created(AdRequest $request): void
    {
        try {
            $recipients = User::permission('ad-requests.view')->get();
            if ($recipients->isEmpty()) {
                return;
            }

            Notification::send($recipients, new NewAdRequestNotification(
                $request->id,
                $request->company_name,
            ));
        } catch (\Throwable $e) {
            Log::warning('ad request notification dispatch failed', [
                'ad_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
