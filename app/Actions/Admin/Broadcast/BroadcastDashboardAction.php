<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Enums\BroadcastKind;
use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Support\Broadcast\BroadcastPresenceControl;

/**
 * مركز عمليات البثّ (B9) — تجميع تشغيليّ للوحة القيادة (مرآة VideoDashboardAction).
 * يُجمّع ما لا يمكن اشتقاقه من نقاط القائمة وحدها: حالة الجمهور المُغلق (Redis، غير
 * مكشوفة في مورد القائمة)، عددات المشتركين، وملخّص القنوات/الصحّة. المجموعات محدودة
 * (المباشر/المجدول/الفاشل قليلة عادةً) فالاستعلامات رخيصة — لا تخزين مؤقّت (طزاجة
 * مركز العمليات أهمّ؛ الواجهة تستطلع دورياً).
 */
final class BroadcastDashboardAction
{
    private const CAP = 50;

    /** @return array<string,mixed> */
    public function handle(): array
    {
        $statusCounts = $this->statusCounts();

        return [
            'status_counts' => $statusCounts,
            'live' => $this->live(),
            'scheduled_today' => $this->scheduledToday(),
            'channels' => [
                'tv' => $this->channelOverview(BroadcastKind::Tv->value),
                'radio' => $this->channelOverview(BroadcastKind::Radio->value),
            ],
            'health_alerts' => $this->healthAlerts(),
            'audience' => ['closed' => $this->closedAudiences()],
            'notifications' => [
                'global_subscribers' => BroadcastNotificationSubscription::query()->global()->count(),
                'upcoming_with_reminders' => $this->upcomingWithReminders(),
            ],
            'totals' => [
                'live_viewers' => (int) Broadcast::query()->where('status', BroadcastStatus::Live->value)->sum('viewer_count'),
                'live' => $statusCounts['live'],
                'scheduled' => $statusCounts['scheduled'],
                'failed' => $statusCounts['failed'],
            ],
        ];
    }

    /** @return array<string,int> عدّ لكل حالة (الكلّ مصفّر افتراضياً). */
    private function statusCounts(): array
    {
        $counts = Broadcast::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $result = [];
        foreach (BroadcastStatus::values() as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }

    /** @return array<int,array<string,mixed>> البثوث المباشرة الآن (مع حالة الجمهور). */
    private function live(): array
    {
        return Broadcast::query()
            ->where('status', BroadcastStatus::Live->value)
            ->orderByDesc('is_featured')
            ->orderByDesc('started_at')
            ->limit(self::CAP)
            ->get()
            ->map(fn (Broadcast $b): array => [
                'id' => $b->id,
                'title' => $b->title,
                'slug' => $b->slug,
                'kind' => $b->kind->value,
                'is_featured' => (bool) $b->is_featured,
                'viewer_count' => (int) $b->viewer_count,
                'started_at' => $b->started_at?->toISOString(),
                'health' => $this->health($b),
                'audience_closed' => BroadcastPresenceControl::isClosed($b->id),
            ])
            ->all();
    }

    /** @return array<int,array<string,mixed>> المجدول خلال الـ 24 ساعة القادمة. */
    private function scheduledToday(): array
    {
        return Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [now(), now()->addDay()])
            ->orderBy('scheduled_at')
            ->limit(self::CAP)
            ->withCount('notificationSubscriptions')
            ->get()
            ->map(fn (Broadcast $b): array => [
                'id' => $b->id,
                'title' => $b->title,
                'kind' => $b->kind->value,
                'scheduled_at' => $b->scheduled_at?->toISOString(),
                'reminder_subscribers' => (int) ($b->notification_subscriptions_count ?? 0),
                'reminder_dispatched' => $b->reminder_dispatched_at !== null,
            ])
            ->all();
    }

    /** @return array{live:int,offline:int,failed:int,total:int} */
    private function channelOverview(string $kind): array
    {
        $counts = Broadcast::query()
            ->where('kind', $kind)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        return [
            'live' => (int) ($counts[BroadcastStatus::Live->value] ?? 0),
            'offline' => (int) ($counts[BroadcastStatus::Offline->value] ?? 0),
            'failed' => (int) ($counts[BroadcastStatus::Failed->value] ?? 0),
            'total' => (int) $counts->sum(),
        ];
    }

    /** @return array<int,array<string,mixed>> تنبيهات الصحّة (الفاشل + آخر سبب). */
    private function healthAlerts(): array
    {
        return Broadcast::query()
            ->where('status', BroadcastStatus::Failed->value)
            ->orderByDesc('last_health_check_at')
            ->limit(self::CAP)
            ->get()
            ->map(fn (Broadcast $b): array => array_merge(
                ['id' => $b->id, 'title' => $b->title, 'kind' => $b->kind->value],
                $this->health($b),
            ))
            ->all();
    }

    /** @return array<int,array{id:int,title:string}> البثوث المُغلق جمهورها (Redis). */
    private function closedAudiences(): array
    {
        return Broadcast::query()
            ->whereIn('status', [BroadcastStatus::Live->value, BroadcastStatus::Scheduled->value, BroadcastStatus::Offline->value])
            ->limit(200)
            ->get(['id', 'title'])
            ->filter(fn (Broadcast $b): bool => BroadcastPresenceControl::isClosed($b->id))
            ->map(fn (Broadcast $b): array => ['id' => $b->id, 'title' => $b->title])
            ->values()
            ->all();
    }

    private function upcomingWithReminders(): int
    {
        return Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->whereHas('notificationSubscriptions')
            ->count();
    }

    /** @return array{status:?string,message:?string,checked_at:?string} */
    private function health(Broadcast $b): array
    {
        return [
            'status' => $b->last_health_status,
            'message' => $b->last_health_message,
            'checked_at' => $b->last_health_check_at?->toISOString(),
        ];
    }
}
