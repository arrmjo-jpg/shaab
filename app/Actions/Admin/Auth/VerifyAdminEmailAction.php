<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * يؤكّد بريد الإداري عبر رابط موقّع، ثم يعيد التوجيه للوحة الإدارة.
 * المسار محميّ بـ middleware: signed (التحقق من التوقيع/الانتهاء).
 */
class VerifyAdminEmailAction
{
    public function handle(int $id, string $hash): RedirectResponse
    {
        $loginUrl = rtrim((string) config('frontend.admin_url'), '/').'/login';
        $user = User::find($id);

        if (
            $user === null
            || ! hash_equals(sha1($user->getEmailForPasswordReset()), $hash)
        ) {
            return redirect()->away($loginUrl.'?verified=0');
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->away($loginUrl.'?verified=1');
    }
}
