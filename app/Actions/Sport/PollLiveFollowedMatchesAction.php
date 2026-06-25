<?php

declare(strict_types=1);

namespace App\Actions\Sport;

use App\Models\FollowNotification;
use App\Models\SportFixture;
use App\Models\User;
use App\Notifications\MatchEventNotification;
use App\Support\Sport\FollowerResolver;
use App\Support\Sport\Sport365Client;
use Illuminate\Support\Facades\Cache;

/**
 * استطلاع المباريات الحيّة المتابَعة للأحداث المباشرة (أهداف/بطاقات) — الكتلة C. الاختيار **بـ`next_poll_at` فقط**
 * (تُهيَّأ من الانطلاق في المزامنة): مباريات `next_poll_at<=now` (غير الحيّة لا تُختار). لكلّ مباراة: لقطة 365،
 * تحديث الحالة، **إيقاف فوريّ عند الانتهاء** (next_poll_at=null) وإلا **45ث**. يُعالَج **كلّ حدث** عبر سجلّ المنع
 * (firstOrCreate على المفتاح المُركَّب) ⇒ **إعادة ترتيب الأحداث لا تُنتج إشعارات إضافية** (لا اعتماد على order/num).
 * `last_event_id`=عدد الأحداث (مؤشّر تقدّم فقط، لا مصدر حقيقة). قفل دفعة + idempotent.
 *
 * @return int عدد إشعارات الأحداث المُرسَلة
 */
final class PollLiveFollowedMatchesAction
{
    /** الأنواع المُشعَر بها: هدف(1)/بطاقة صفراء(2)/حمراء(3) — لا تبديل/قائم (تفادي إزعاج). */
    private const NOTIFIABLE_TYPES = [1, 2, 3];

    private const POLL_INTERVAL_SECONDS = 45;

    public function __construct(
        private readonly Sport365Client $client,
        private readonly FollowerResolver $resolver,
    ) {}

    public function handle(): int
    {
        $lock = Cache::lock('follow-poll-live', 55);
        if (! $lock->get()) {
            return 0; // تشغيل آخر جارٍ — تخطٍّ آمن
        }

        try {
            $due = SportFixture::query()
                ->whereNotNull('next_poll_at')
                ->where('next_poll_at', '<=', now())
                ->orderBy('next_poll_at')
                ->limit(100)
                ->get();

            if ($due->isEmpty()) {
                return 0;
            }

            $playerFollowersByTeam = $this->resolver->playerFollowersByTeam();
            $notified = 0;

            foreach ($due as $fixture) {
                $snapshot = $this->client->gameSnapshot((int) $fixture->game_id);

                if ($snapshot === null) {
                    // فشل الجلب: أجِّل دون إيقاف (إعادة محاولة الدورة القادمة).
                    $fixture->update(['next_poll_at' => now()->addSeconds(self::POLL_INTERVAL_SECONDS)]);

                    continue;
                }

                $finished = $snapshot['status'] === 'finished';
                $fixture->status = $snapshot['status'];
                // إيقاف فوريّ عند الانتهاء؛ وإلا الاستطلاع كلّ 45ث.
                $fixture->next_poll_at = $finished ? null : now()->addSeconds(self::POLL_INTERVAL_SECONDS);
                // مؤشّر تقدّم فقط (عدد الأحداث — مستقلّ عن الترتيب)؛ ليس أساس المنع.
                $fixture->last_event_id = count($snapshot['events']);
                $fixture->save();

                $followers = null; // كسول: يُحسَب مرّة عند أوّل حدث مؤهَّل لهذه المباراة.
                foreach ($snapshot['events'] as $event) {
                    if (! in_array($event['event_type_id'], self::NOTIFIABLE_TYPES, true)) {
                        continue; // أهداف/بطاقات فقط
                    }
                    $followers ??= $this->resolver->followersOfFixture($fixture, $playerFollowersByTeam);

                    foreach ($followers as $userId) {
                        // منع التكرار النهائيّ عبر المفتاح المُركَّب (لا order): حدثٌ بترتيب مختلف ⇒ نفس المفتاح ⇒ لا إشعار جديد.
                        $record = FollowNotification::firstOrCreate(
                            ['user_id' => $userId, 'dedup_key' => $event['dedup_key']],
                            ['game_id' => $fixture->game_id, 'kind' => 'event', 'event_id' => null, 'sent_at' => now()],
                        );

                        if (! $record->wasRecentlyCreated) {
                            continue;
                        }

                        User::find($userId)?->notify(new MatchEventNotification(
                            (int) $fixture->game_id,
                            $fixture->competition_id !== null ? (int) $fixture->competition_id : null,
                            (int) $event['event_type_id'],
                            (string) $event['label'],
                            (string) $event['minute'],
                            $event['player_name'],
                            $fixture->home_name,
                            $fixture->away_name,
                        ));
                        $notified++;
                    }
                }
            }

            return $notified;
        } finally {
            $lock->release();
        }
    }
}
