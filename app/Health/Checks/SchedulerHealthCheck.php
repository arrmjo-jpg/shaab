<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Support\Scheduler\SchedulerRegistry;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * صحّة المهام المجدوَلة الحرجة (Phase 5 — تشخيص). يقرأ حالة التشغيل المسجَّلة
 * (ScheduledTask عبر SchedulerState) لا heartbeat منفصل.
 *
 * لكل مهمة critical مُفعّلة:
 *  - آخر تشغيل فشل ⇒ فشل صحّي (مع آخر خطأ).
 *  - تأخّرت عن آخر وقت متوقّع (cron) بهامش تسامح ⇒ فشل صحّي (تأخّر/تعليق).
 *
 * المهام التي لم تُشغَّل قطّ تُتجاهَل عمداً (تفادي إنذار كاذب بعد النشر مباشرة)؛
 * كشف توقّف المُجدوِل كلياً مسؤولية Spatie ScheduleCheck (heartbeat) المنفصل.
 */
class SchedulerHealthCheck extends Check
{
    /** هامش تسامح للتأخّر (دقائق) فوق آخر وقت متوقّع. */
    private const GRACE_MINUTES = 10;

    public function run(): Result
    {
        $problems = [];
        $checked = 0;

        foreach (SchedulerRegistry::all() as $key => $def) {
            if (! ($def['critical'] ?? false) || ! SchedulerRegistry::isEnabled($key)) {
                continue;
            }
            $checked++;

            $state = SchedulerRegistry::state($key);

            if ($state->last_status === 'failed') {
                $problems[] = $key.': last run failed'
                    .($state->last_error ? ' ('.mb_substr((string) $state->last_error, 0, 120).')' : '');

                continue;
            }

            // تأخّر: شُغِّلت سابقاً لكن قبل آخر وقت متوقّع ناقص الهامش.
            $expected = SchedulerRegistry::previousExpectedAt($def['cron']);
            if ($expected !== null
                && $state->last_run_at !== null
                && $state->last_run_at->lt($expected->copy()->subMinutes(self::GRACE_MINUTES))) {
                $problems[] = $key.': overdue (last run '.$state->last_run_at->diffForHumans().')';
            }
        }

        $result = Result::make()->meta([
            'critical_tasks' => $checked,
            'problems' => $problems,
        ]);

        if ($problems !== []) {
            return $result->failed('Scheduler issues — '.implode('; ', $problems));
        }

        return $result->ok("All {$checked} critical scheduled task(s) healthy.");
    }
}
