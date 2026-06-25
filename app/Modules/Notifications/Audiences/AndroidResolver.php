<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Audiences;

use App\Modules\Notifications\Enums\AudienceType;
use App\Modules\Notifications\Enums\DevicePlatform;
use App\Modules\Notifications\Support\AudienceResult;
use Illuminate\Database\Eloquent\Builder;

/** أجهزة Android النشطة (cohort أجهزة — push). */
final class AndroidResolver extends AbstractAudienceResolver
{
    public function type(): AudienceType
    {
        return AudienceType::Android;
    }

    public function describe(array $params): AudienceResult
    {
        return AudienceResult::cohort(AudienceType::Android, []);
    }

    public function deviceQuery(AudienceResult $audience): ?Builder
    {
        return $this->activeDevices()->where('platform', DevicePlatform::Android->value);
    }
}
