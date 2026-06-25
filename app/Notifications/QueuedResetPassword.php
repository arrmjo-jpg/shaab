<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * نسخة مُصفّفة من إشعار إعادة تعيين كلمة المرور — لا تحجب طلب الـ HTTP
 * على نداء SMTP. التخصيصات العالمية (createUrlUsing / toMailUsing) في
 * AppServiceProvider تظل سارية لأنها مُسجَّلة على الصنف الأساسي.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
