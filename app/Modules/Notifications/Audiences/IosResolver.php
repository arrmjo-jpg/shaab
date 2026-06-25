<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Enums\DevicePlatform;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/** أجهزة iOS النشطة (cohort أجهزة — push). */
final class IosResolver extends AbstractAudienceResolver
{
    public function type(): AudienceType
    {
        return AudienceType::Ios;
    }

    public function describe(array $params): AudienceResult
    {
        return AudienceResult::cohort(AudienceType::Ios, []);
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeDevices()->where('platform', DevicePlatform::Ios->value);
    }
}
