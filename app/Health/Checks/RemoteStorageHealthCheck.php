<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Support\Media\RemoteStorage;
use App\Support\Media\RemoteStorageHealth;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * صحّة التخزين البعيد (المرآة) — يُدفّئ كاش الصحّة ويمنح رؤية تشغيلية.
 *
 * fail-safe: عدم توفّر البعيد ليس فشلاً حرجاً (التسليم يرتدّ للمحلّي تلقائياً)،
 * لذا يُرجَع warning لا failed — لا يكسر شيئاً ولا يُطلِق إنذار توقّف.
 */
class RemoteStorageHealthCheck extends Check
{
    public function run(): Result
    {
        $result = Result::make();

        if (! RemoteStorage::enabled()) {
            return $result->ok('Remote storage disabled — serving local (canonical).');
        }

        // يستدعي البروب ويُخزّنه مؤقتاً (تدفئة الكاش).
        return RemoteStorageHealth::isHealthy()
            ? $result->ok('Remote storage healthy.')
            : $result->warning('Remote storage unreachable — delivery automatically falls back to local.');
    }
}
