<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\ClientSource;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * يسجّل سياق طلب إعادة تعيين كلمة المرور (المصدر + IP + المتصفّح + الوقت)
 * في سجل النشاط — لمراجعة أمنية. آمن حتى لو لم يوجد مستخدم.
 */
final class PasswordResetAudit
{
    public static function record(string $email, ?User $user = null): void
    {
        $source = ClientSource::fromRequest();

        $properties = [
            'source' => ClientSource::key($source),
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512),
            'requested_email' => $email,
            'timestamp' => now()->toISOString(),
        ];

        $log = activity('auth')
            ->event('password_reset_requested')
            ->withProperties($properties);

        if ($user !== null) {
            $log->causedBy($user)->performedOn($user);
        }

        $log->log(__('auth.activity.password_reset_requested'));

        // يُخزَّن للسياق ليُقرأ وقت بناء البريد (يبقى ضمن نفس الطلب)
        app()->instance('auth.reset_origin', [
            'source' => $properties['source'],
            'ip' => $properties['ip'],
        ]);
    }
}
