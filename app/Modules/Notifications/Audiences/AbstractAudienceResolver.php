<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Enums\UserStatus;
use App\Models\User;
use App\Modules\Notifications\Contracts\AudienceResolver;
use App\Modules\Notifications\Models\MobileDevice;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/**
 * أساس مُحلِّلات الجمهور — افتراضات null للاستعلامات (الأبناء يبنون ما يلزم وقت التنفيذ) + مُعينات
 * مشتركة. الاستعلامات حيّة عابرة (لا تُسلسَل، لا تُمادّى) — تُستهلَك مُجزّأة في الـBinder/Job.
 */
abstract class AbstractAudienceResolver implements AudienceResolver
{
    public function userQuery(AudienceResult $audience): ?Builder
    {
        return null;
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return null;
    }

    /** أجهزة push نشطة بتوكن (أساس مشترك). @return Builder<MobileDevice> */
    protected function activeDevices(): Builder
    {
        return MobileDevice::query()->where('is_active', true)->whereNotNull('fcm_token');
    }

    /** مستخدمون نشطون (غير موقوفين/محظورين). @return Builder<User> */
    protected function activeUsers(): Builder
    {
        return User::query()->where('status', UserStatus::Active->value);
    }
}
