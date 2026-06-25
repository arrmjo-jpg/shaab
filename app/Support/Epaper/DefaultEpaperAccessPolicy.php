<?php

declare(strict_types=1);

namespace App\Support\Epaper;

use App\Enums\EpaperAccessLevel;
use App\Models\Epaper;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * السياسة الافتراضية — لا تفترض محرّك اشتراكات (لا تزييف للاشتراك عبر RBAC عامّ):
 *  - public: الزائر يرى.
 *  - subscriber: مرفوض ما لم يربط المضيف Binding مخصّصاً (هنا الافتراض: لا استحقاق).
 *  - private: مرفوض إلا لإداريّ يملك epapers.view.
 * التنزيل يتبع رؤية العدد: الأعداد العامّة (public) قابلة للتنزيل للجميع (قرار المالك)؛
 * subscriber/private تبقى للإداريّ حتى يربط المضيف محرّك اشتراك فعليّ.
 */
final class DefaultEpaperAccessPolicy implements EpaperAccessPolicy
{
    public function canView(?Authenticatable $user, Epaper $issue): bool
    {
        if ($this->isStaff($user)) {
            return true; // إداريّ: غير مقيَّد عبر كل المستويات
        }

        return $issue->access_level === EpaperAccessLevel::Public;
    }

    public function canDownload(?Authenticatable $user, Epaper $issue): bool
    {
        if ($this->isStaff($user)) {
            return true; // إداريّ: غير مقيَّد عبر كل المستويات
        }

        // الأعداد العامّة قابلة للتنزيل للجميع (قرار المالك)؛ subscriber/private مرفوضة.
        return $issue->access_level === EpaperAccessLevel::Public;
    }

    private function isStaff(?Authenticatable $user): bool
    {
        return $user instanceof Authorizable && $user->can('epapers.view');
    }
}
