<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * مراقبة صحّة مصادر البثّ (B3) — تُبرز عدد عمليات البثّ ذات المصدر الفاشل عبر نقطة
 * /system/health، فتنطلق إشعارات الفشل (mail/slack) المضبوطة في config/health.php.
 * مسار التنبيه التشغيلي الأصلي للمنصّة (لا اختراع).
 */
class BroadcastSourceHealthCheck extends Check
{
    public function run(): Result
    {
        $failed = Broadcast::query()
            ->where('status', BroadcastStatus::Failed->value)
            ->count();

        $result = Result::make()
            ->meta(['failed' => $failed])
            ->shortSummary("{$failed} failed");

        if ($failed > 0) {
            return $result->failed(
                "{$failed} broadcast(s) have a failed source — health monitoring detected an unreachable/invalid stream."
            );
        }

        return $result->ok('All monitored broadcasts healthy.');
    }
}
