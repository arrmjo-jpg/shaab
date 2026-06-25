<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\GeneralSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestMailConnectionAction
{
    public function handle(string $to): JsonResponse
    {
        $s = app(GeneralSettings::class);

        $from = $s->mail_from_email !== '' ? $s->mail_from_email : $s->site_email;
        if ($from === '') {
            // سبب شائع: لم يُضبط بريد المُرسِل
            return ApiResponse::error(__('setting.mail_test_failed'), [
                'reason' => 'sender email (from) is not configured',
            ], 422);
        }

        $encryption = in_array($s->mail_encryption, ['', 'null'], true)
            ? null
            : $s->mail_encryption;

        // ناقل بريد وقت التشغيل من الإعدادات المخزَّنة
        Config::set('mail.mailers.settings_smtp', [
            'transport' => 'smtp',
            'host' => $s->mail_host,
            'port' => $s->mail_port,
            'encryption' => $encryption,
            'username' => $s->mail_username,
            'password' => $s->mail_password,
            'timeout' => 10,
        ]);

        try {
            Mail::mailer('settings_smtp')->raw(
                __('setting.mail_test_body'),
                function ($message) use ($to, $s, $from): void {
                    $message->to($to)
                        ->from($from, $s->mail_from_name)
                        ->subject(__('setting.mail_test_subject'));
                }
            );
        } catch (Throwable $e) {
            // كشف السبب الفعلي (نقطة تشخيص للمدير) + تسجيله
            Log::warning('SMTP test failed', ['error' => $e->getMessage()]);

            return ApiResponse::error(__('setting.mail_test_failed'), [
                'reason' => $e->getMessage(),
            ], 422);
        }

        return ApiResponse::success(__('setting.mail_test_success'));
    }
}
