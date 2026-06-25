<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/** كلّ المخاطَبين: كلّ الأجهزة النشطة (push) / كلّ المستخدمين النشطين (per-recipient). */
final class AllUsersResolver extends AbstractAudienceResolver
{
    public function type(): AudienceType
    {
        return AudienceType::All;
    }

    public function describe(array $params): AudienceResult
    {
        return AudienceResult::cohort(AudienceType::All, []);
    }

    public function userQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeUsers();
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeDevices();
    }
}
