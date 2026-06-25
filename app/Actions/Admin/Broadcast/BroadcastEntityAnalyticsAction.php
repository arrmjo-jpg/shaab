<?php

declare(strict_types=1);

namespace App\Actions\Admin\Broadcast;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastNotificationSubscription;
use App\Models\BroadcastViewerSample;
use App\Support\Analytics\AnalyticsRange;
use App\Support\Analytics\DailyEngagementReader;
use App\Support\Broadcast\BroadcastPresence;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تحليلات بثّ واحد (سياقيّة، داخل النطاق) — مقاييس حقيقية فقط، مع تصريح صادق بالحدود:
 *
 *  • الأداء الحيّ: الحاليّ (محرّك الحضور، طازج) + الذروة (عمود دائم) + المتوسّط/منحنى
 *    التزامن (عيّنات broadcast_viewer_samples، إلى-الأمام). الفريدون التقريبيون: مؤجّل
 *    بصدق (الحضور تجميعيّ تقريبيّ، لا اتّحاد هويّات).
 *  • الصحّة: عدّ الإخفاق/التعافي + ملخّص الكمون + أحداث حديثة (broadcast_health_checks).
 *  • الإشراف: عدّ الطرد/الحظر/الإغلاق/الطوارئ + أحداث حديثة (سجلّ التدقيق الدائم).
 *  • الخطّ الزمني: مجدوَل/فعليّ + تأخير البدء + المدّة.
 *  • الإشعارات: المشتركون + علامات الإطلاق. التسليم/التحويل: مؤجّل (FCM غير مُهيّأ).
 *
 * كاش قصير للمجاميع الثقيلة؛ «الحاليّ» يُقرأ طازجاً خارج الكاش (Redis رخيص).
 */
class BroadcastEntityAnalyticsAction
{
    /** أحداث الإشراف الدائمة (BroadcastModerationAudit) في سجلّ التدقيق. */
    private const MOD_EVENTS = [
        'viewer_kicked', 'viewer_banned', 'viewer_unbanned',
        'audience_closed', 'audience_reopened', 'emergency_shutdown',
    ];

    private const CURVE_MAX_POINTS = 240;

    public function handle(Broadcast $broadcast, ?string $range, ?string $from = null, ?string $to = null): JsonResponse
    {
        $window = AnalyticsRange::resolve($range, $from, $to);

        $data = Cache::remember(
            "broadcast:analytics:entity:{$broadcast->id}:{$window->key()}:v1",
            CacheTtl::REALTIME,
            fn (): array => $this->compute($broadcast, $window),
        );

        // الحاليّ طازج دائماً (للبثّ المباشر) — قراءة حضور رخيصة خارج الكاش.
        $data['live_performance']['current_viewers'] = $broadcast->status === BroadcastStatus::Live
            ? BroadcastPresence::count($broadcast->id)
            : 0;

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(Broadcast $broadcast, AnalyticsRange $window): array
    {
        $broadcast->loadMissing(['category', 'engagementCounter']);
        $metrics = $broadcast->engagementMetrics();
        $series = DailyEngagementReader::read($broadcast->getMorphClass(), $broadcast->id, $window);

        return [
            'entity' => [
                'id' => $broadcast->id,
                'title' => $broadcast->title,
                'slug' => $broadcast->slug,
                'kind' => $broadcast->kind->value,
                'status' => $broadcast->status->value,
                'is_featured' => (bool) $broadcast->is_featured,
            ],
            'live_performance' => $this->livePerformance($broadcast, $window),
            'engagement' => [
                'likes' => $metrics['likes'],
                'dislikes' => $metrics['dislikes'],
                'favorites' => $metrics['favorites'],
            ],
            'concurrency' => $this->concurrency($broadcast, $window),
            'engagement_trend' => [
                'window' => $window->toArray(),
                'forward_only' => true,
                'points' => $series['points'],
                'totals' => $series['totals'],
            ],
            'timeline' => $this->timeline($broadcast),
            'health' => $this->health($broadcast, $window),
            'moderation' => $this->moderation($broadcast),
            'notifications' => $this->notifications($broadcast),
        ];
    }

    /** @return array<string,mixed> */
    private function livePerformance(Broadcast $broadcast, AnalyticsRange $window): array
    {
        $agg = BroadcastViewerSample::query()
            ->where('broadcast_id', $broadcast->id)
            ->whereBetween('sampled_at', [$window->from, $window->to->endOfDay()])
            ->selectRaw('COUNT(*) as c, AVG(viewers) as a, MAX(viewers) as m')
            ->first();

        return [
            'current_viewers' => 0, // يُحقَن طازجاً في handle()
            'peak_all_time' => (int) $broadcast->peak_viewer_count,
            'peak_in_window' => (int) ($agg->m ?? 0),
            'average_concurrent' => (int) round((float) ($agg->a ?? 0)),
            'sample_count' => (int) ($agg->c ?? 0),
            'viewer_count_snapshot' => (int) $broadcast->viewer_count,
            // الفريدون التقريبيون غير مُتعقَّبين (الحضور تجميعيّ تقريبيّ — لا اتّحاد هويّات).
            'unique_viewers' => ['available' => false, 'reason' => 'not_tracked'],
        ];
    }

    /** @return array<string,mixed> منحنى التزامن (آخر العيّنات في النطاق، محدود). */
    private function concurrency(Broadcast $broadcast, AnalyticsRange $window): array
    {
        $points = BroadcastViewerSample::query()
            ->where('broadcast_id', $broadcast->id)
            ->whereBetween('sampled_at', [$window->from, $window->to->endOfDay()])
            ->orderByDesc('sampled_at')
            ->limit(self::CURVE_MAX_POINTS)
            ->get(['viewers', 'sampled_at'])
            ->reverse()
            ->values()
            ->map(fn (BroadcastViewerSample $s): array => [
                'at' => $s->sampled_at?->toISOString(),
                'viewers' => (int) $s->viewers,
            ])->all();

        return [
            'window' => $window->toArray(),
            'forward_only' => true,
            'note' => 'concurrency', // منحنى تزامن (لا انضمام/مغادرة دقيقَين)
            'points' => $points,
        ];
    }

    /** @return array<string,mixed> */
    private function timeline(Broadcast $broadcast): array
    {
        $scheduled = $broadcast->scheduled_at;
        $started = $broadcast->started_at;
        $ended = $broadcast->ended_at;
        $isLive = $broadcast->status === BroadcastStatus::Live;

        $startDelay = ($scheduled !== null && $started !== null)
            ? $started->getTimestamp() - $scheduled->getTimestamp()
            : null;

        $end = $ended ?? ($isLive ? now() : null);
        $duration = ($started !== null && $end !== null && $end->getTimestamp() >= $started->getTimestamp())
            ? $end->getTimestamp() - $started->getTimestamp()
            : null;

        return [
            'scheduled_at' => $scheduled?->toISOString(),
            'started_at' => $started?->toISOString(),
            'ended_at' => $ended?->toISOString(),
            'is_live' => $isLive,
            'start_delay_seconds' => $startDelay,   // موجب = بدأ متأخّراً
            'duration_seconds' => $duration,        // إجماليّ (لا يطرح فترات الإيقاف المؤقّت)
        ];
    }

    /** @return array<string,mixed> */
    private function health(Broadcast $broadcast, AnalyticsRange $window): array
    {
        $from = $window->from;
        $to = $window->to->endOfDay();

        $agg = DB::table('broadcast_health_checks')
            ->where('broadcast_id', $broadcast->id)
            ->whereBetween('checked_at', [$from, $to])
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) as healthy,
                AVG(latency_ms) as avg_latency,
                MAX(latency_ms) as max_latency")
            ->first();

        // آخر الفحوصات (لعرض الأحداث + عدّ التعافي من الانتقالات failed→healthy).
        $recent = DB::table('broadcast_health_checks')
            ->where('broadcast_id', $broadcast->id)
            ->whereBetween('checked_at', [$from, $to])
            ->orderByDesc('checked_at')
            ->limit(50)
            ->get(['status', 'latency_ms', 'failure_reason', 'checked_at']);

        $recoveries = 0;
        $prev = null;
        foreach ($recent->reverse()->values() as $row) {
            if ($prev === 'failed' && $row->status === 'healthy') {
                $recoveries++;
            }
            $prev = $row->status;
        }

        return [
            'window' => $window->toArray(),
            'retention_days' => (int) config('broadcast.health.history_retention_days', 30),
            'last_status' => $broadcast->last_health_status,
            'last_message' => $broadcast->last_health_message,
            'last_checked_at' => $broadcast->last_health_check_at?->toISOString(),
            'consecutive_failures' => (int) $broadcast->health_consecutive_failures,
            'failure_count' => (int) ($agg->failed ?? 0),
            'healthy_count' => (int) ($agg->healthy ?? 0),
            'check_count' => (int) ($agg->total ?? 0),
            'recovery_count' => $recoveries, // ضمن آخر الفحوصات
            'avg_latency_ms' => $agg->avg_latency !== null ? (int) round((float) $agg->avg_latency) : null,
            'max_latency_ms' => $agg->max_latency !== null ? (int) $agg->max_latency : null,
            'recent_events' => $recent->take(20)->map(fn ($r): array => [
                'status' => $r->status,
                'latency_ms' => $r->latency_ms !== null ? (int) $r->latency_ms : null,
                'reason' => $r->failure_reason,
                'at' => $r->checked_at,
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function moderation(Broadcast $broadcast): array
    {
        $counts = DB::table('activity_log')
            ->where('log_name', 'broadcast')
            ->where('subject_id', $broadcast->id)
            ->whereIn('event', self::MOD_EVENTS)
            ->groupBy('event')
            ->selectRaw('event, COUNT(*) as aggregate')
            ->pluck('aggregate', 'event');

        $recent = DB::table('activity_log')
            ->where('log_name', 'broadcast')
            ->where('subject_id', $broadcast->id)
            ->whereIn('event', self::MOD_EVENTS)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['event', 'description', 'properties', 'created_at'])
            ->map(function ($row): array {
                $props = json_decode((string) $row->properties, true) ?: [];

                return [
                    'event' => $row->event,
                    'description' => $row->description,
                    'member' => $props['member'] ?? null,
                    'reason' => $props['reason'] ?? null,
                    'at' => $row->created_at,
                ];
            })->all();

        return [
            'kicks' => (int) ($counts['viewer_kicked'] ?? 0),
            'bans' => (int) ($counts['viewer_banned'] ?? 0),
            'unbans' => (int) ($counts['viewer_unbanned'] ?? 0),
            'closures' => (int) ($counts['audience_closed'] ?? 0),
            'reopens' => (int) ($counts['audience_reopened'] ?? 0),
            'emergency_shutdowns' => (int) ($counts['emergency_shutdown'] ?? 0),
            'recent_events' => $recent,
        ];
    }

    /** @return array<string,mixed> */
    private function notifications(Broadcast $broadcast): array
    {
        return [
            'reminder_subscribers' => $broadcast->notificationSubscriptions()->count(),
            'global_subscribers' => BroadcastNotificationSubscription::query()->global()->count(),
            'live_notified_at' => $broadcast->live_notified_at?->toISOString(),
            'reminder_dispatched_at' => $broadcast->reminder_dispatched_at?->toISOString(),
            // التسليم/الفتح/التحويل غير قابلة للقياس: FCM بوّابة سجلّ فقط (غير مُهيّأ)،
            // ونموذج المواضيع لا يلتقط إيصالات لكل مستخدم.
            'delivery' => ['available' => false, 'reason' => 'fcm_not_provisioned'],
        ];
    }
}
