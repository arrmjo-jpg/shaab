<?php

declare(strict_types=1);

namespace App\Actions\Sport;

use App\Models\FollowNotification;
use App\Models\SportFixture;
use App\Models\User;
use App\Notifications\MatchReminderNotification;
use App\Support\Sport\FollowerResolver;
use Illuminate\Support\Facades\Cache;

/**
 * تذكير ما قبل المباراة (الكتلة B) — يجد مباريات المرآة الداخلة نافذة التذكير، يجمع متابِعيها عبر FollowerResolver
 * المشترك (مباراة/بطولة/فريق + لاعب→فريقه)، ويُشعر كلّ متابِع **مرّةً واحدة** (firstOrCreate على dedup_key +
 * قيد unique). قفل دفعة `follow-reminders` يمنع التداخل، idempotent، آمن عند الفراغ. يُدار بـSchedulerRegistry.
 *
 * @return int عدد الإشعارات المُرسَلة
 */
final class DispatchFollowRemindersAction
{
    public function __construct(private readonly FollowerResolver $resolver) {}

    public function handle(): int
    {
        $lock = Cache::lock('follow-reminders', 55);
        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        try {
            $lead = max(1, (int) config('sport.reminder_lead_minutes', 30));
            $windowEnd = now()->addMinutes($lead);
            $playerFollowersByTeam = $this->resolver->playerFollowersByTeam();

            $sent = 0;
            SportFixture::query()
                ->where('status', 'scheduled')
                ->whereNotNull('start_at')
                ->where('start_at', '>', now())       // لم تبدأ
                ->where('start_at', '<=', $windowEnd)  // دخلت نافذة التذكير
                ->orderBy('start_at')
                ->chunkById(50, function ($fixtures) use (&$sent, $playerFollowersByTeam): void {
                    foreach ($fixtures as $fixture) {
                        foreach ($this->resolver->followersOfFixture($fixture, $playerFollowersByTeam) as $userId) {
                            $record = FollowNotification::firstOrCreate(
                                ['user_id' => $userId, 'dedup_key' => "reminder:{$fixture->game_id}"],
                                ['game_id' => $fixture->game_id, 'kind' => 'reminder', 'event_id' => null, 'sent_at' => now()],
                            );

                            if (! $record->wasRecentlyCreated) {
                                continue; // أُشعِر سابقًا (منع تكرار)
                            }

                            User::find($userId)?->notify(new MatchReminderNotification(
                                (int) $fixture->game_id,
                                $fixture->competition_id !== null ? (int) $fixture->competition_id : null,
                                $fixture->home_name,
                                $fixture->away_name,
                                $fixture->start_at?->toIso8601String(),
                            ));
                            $sent++;
                        }
                    }
                });

            return $sent;
        } finally {
            $lock->release();
        }
    }
}
