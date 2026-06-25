<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\ClientSource;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * تسجيل نشاط مصادقة/حساب مُعقّم (مفاتيح آمنة فقط) في سجل النشاط.
 * يُستخدم للدخول، تعديل البروفيل، تغيير كلمة المرور…
 */
final class AuthActivity
{
    public static function log(string $event, User $user): void
    {
        $context = [
            'source' => ClientSource::key(ClientSource::fromRequest()),
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 180),
            'timestamp' => now()->toISOString(),
        ];

        activity('auth')
            ->causedBy($user)
            ->event($event)
            ->withProperties($context)
            ->log(__('auth.activity.'.$event));
    }
}
