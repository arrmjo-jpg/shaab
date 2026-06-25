<?php

use App\Support\Scheduler\SchedulerRegistry;
use App\Support\Scheduler\SchedulerState;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| المُجدوِل يبقى آمراً (code-driven). كل مهمة مُعرّفة في SchedulerRegistry
| فقط؛ الواجهة تتحكّم بـ enabled لا غير. لا حقن cron ولا تنفيذ أوامر حرّة.
*/
foreach (SchedulerRegistry::all() as $key => $def) {
    $started = [];

    Schedule::command($def['command'])
        ->cron($def['cron'])
        ->when(fn (): bool => SchedulerRegistry::isEnabled($key))
        // لا تداخل، تشغيل في خلفية منفصلة (لا يحجب نبضة المُجدوِل)،
        // وخادم واحد عند التوسّع الأفقي
        ->withoutOverlapping()
        ->runInBackground()
        ->onOneServer()
        ->before(function () use (&$started, $key): void {
            $started[$key] = microtime(true);
            SchedulerState::markRunning($key);
        })
        ->onSuccess(function () use (&$started, $key): void {
            SchedulerState::record($key, true, $started[$key] ?? null);
        })
        ->onFailure(function () use (&$started, $key): void {
            SchedulerState::record($key, false, $started[$key] ?? null, 'scheduled run failed');
        });
}

// فحوصات الصحّة (تشغيليّة — خارج سجلّ المهام القابل للتبديل من الواجهة)
Schedule::command('health:check')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// نبض المُجدوِل: يُحدِّث طابع زمن يقرأه ScheduleCheck لكشف توقّف الـ cron كلياً.
Schedule::command('health:schedule-check-heartbeat')
    ->everyMinute()
    ->onOneServer();

// نبض الطوابير: يُرسل مهمّة heartbeat لكل طابور؛ QueueCheck يفشل إن لم يلتقطها
// عامل (كشف عامل معطّل). فعّال مع Redis؛ مع sync تُنفَّذ فوراً (لا ضجيج).
Schedule::command('health:queue-check-heartbeat')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
