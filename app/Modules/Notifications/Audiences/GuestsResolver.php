<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/** الضيوف: أجهزة نشطة بلا مستخدم (user_id null). لا userQuery (الضيوف بلا مستخدمين). */
final class GuestsResolver extends AbstractAudienceResolver
{
    public function type(): AudienceType
    {
        return AudienceType::Guests;
    }

    public function describe(array $params): AudienceResult
    {
        return AudienceResult::cohort(AudienceType::Guests, []);
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeDevices()->whereNull('user_id');
    }
}
