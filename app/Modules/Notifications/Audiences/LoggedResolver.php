<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/** المستخدمون المسجّلون: مستخدمون نشطون / أجهزتهم المرتبطة (user_id غير null). */
final class LoggedResolver extends AbstractAudienceResolver
{
    public function type(): AudienceType
    {
        return AudienceType::Logged;
    }

    public function describe(array $params): AudienceResult
    {
        return AudienceResult::cohort(AudienceType::Logged, []);
    }

    public function userQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeUsers();
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeDevices()->whereNotNull('user_id');
    }
}
