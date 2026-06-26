import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Activity,
  ArrowRight,
  Ban,
  Bell,
  Clock,
  Eye,
  HeartPulse,
  ShieldAlert,
  ThumbsDown,
  ThumbsUp,
  TrendingUp,
  Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';
import {
  DeferredNotice,
  fmtDuration,
  fmtNum,
  RangeFilter,
  TrendChart,
  type RangeValue,
} from '@/components/analytics/AnalyticsKit';
import { MetricCard, Panel } from '../components/StatPrimitives';
import { useBroadcastEntityAnalytics } from '../hooks';
import type { BroadcastStatus } from '@/types/broadcast.types';

const STATUS_TONE: Record<string, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  scheduled: 'muted',
  live: 'success',
  offline: 'muted',
  ended: 'muted',
  failed: 'destructive',
  archived: 'muted',
};

const MOD_KEYS = ['kicks', 'bans', 'unbans', 'closures', 'reopens', 'emergency_shutdowns'] as const;

/**
 * تحليلات بثّ واحد (سياقيّة) — أداء حيّ (ذروة/متوسّط/منحنى تزامن)، صحّة، إشراف،
 * إشعارات، خطّ زمنيّ. بيانات حقيقية فقط؛ الفريدون/التسليم مؤجّلان بصدق.
 */
export default function BroadcastAnalyticsPage() {
  const { t } = useTranslation('broadcast');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const broadcastId = id ? Number(id) : null;

  const [range, setRange] = useState<RangeValue>({ range: '30d' });
  const [live, setLive] = useState(false);
  const q = useBroadcastEntityAnalytics(broadcastId, range.range, range.from, range.to, live);

  useEffect(() => {
    if (q.data) setLive(q.data.entity.status === 'live');
  }, [q.data]);

  const rangeLabels: Record<string, string> = {
    '24h': t('analytics2.range.24h'),
    '7d': t('analytics2.range.7d'),
    '30d': t('analytics2.range.30d'),
    custom: t('analytics2.range.custom'),
  };

  const status = q.data?.entity.status as BroadcastStatus | undefined;

  const Header = (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="flex items-start gap-3">
        <Button variant="ghost" size="icon" onClick={() => navigate(paths.bcBroadcasts)}>
          <ArrowRight className="h-4 w-4" />
        </Button>
        <div>
          <p className="text-xs font-medium text-muted-foreground">{t('analytics2.title')}</p>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-2xl font-bold">{q.data?.entity.title ?? t('analytics2.loadingTitle')}</h1>
            {status ? <Badge variant={STATUS_TONE[status]}>{t(`status.${status}`)}</Badge> : null}
          </div>
        </div>
      </div>
      <RangeFilter value={range} onChange={setRange} labels={rangeLabels} />
    </header>
  );

  if (q.isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="text-destructive">{t('analytics2.error')}</span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('analytics2.retry')}
          </Button>
        </div>
      </div>
    );
  }

  if (q.isLoading || !q.data) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" />
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-56 w-full" />
          <Skeleton className="h-56 w-full" />
        </div>
      </div>
    );
  }

  const a = q.data;
  const lp = a.live_performance;
  const curvePoints = a.concurrency.points.map((p) => ({
    label: p.at ? p.at.slice(5, 16).replace('T', ' ') : '',
    value: p.viewers,
  }));
  const startDelay = a.timeline.start_delay_seconds;

  return (
    <div className="space-y-6">
      {Header}

      {/* Live performance KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('analytics2.live.current')} value={fmtNum(lp.current_viewers)} icon={Eye} tone="text-red-600 dark:text-red-400" />
        <MetricCard label={t('analytics2.live.peak')} value={fmtNum(lp.peak_all_time)} icon={TrendingUp} tone="text-primary" />
        <MetricCard label={t('analytics2.live.average')} value={fmtNum(lp.average_concurrent)} icon={Users} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('analytics2.live.samples')} value={fmtNum(lp.sample_count)} icon={Activity} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('analytics2.engagement.likes')} value={fmtNum(a.engagement.likes)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('analytics2.engagement.dislikes')} value={fmtNum(a.engagement.dislikes)} icon={ThumbsDown} tone="text-destructive" />
      </div>

      {/* Concurrency curve (forward-only) */}
      <Panel title={t('analytics2.concurrency.title')} subtitle={t('analytics2.concurrency.subtitle')} icon={TrendingUp}>
        <TrendChart points={curvePoints} color="bg-red-500" emptyLabel={t('analytics2.concurrency.empty')} />
        <div className="mt-3">
          <DeferredNotice title={t('analytics2.unique.title')} note={t('analytics2.unique.note')} />
        </div>
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Timeline */}
        <Panel title={t('analytics2.timeline.title')} icon={Clock}>
          <dl className="space-y-2.5 text-sm">
            {([
              ['scheduled', a.timeline.scheduled_at],
              ['started', a.timeline.started_at],
              ['ended', a.timeline.ended_at],
            ] as const).map(([key, value]) => (
              <div key={key} className="flex items-center justify-between gap-3">
                <dt className="text-muted-foreground">{t(`analytics2.timeline.${key}`)}</dt>
                <dd className="font-medium tabular-nums" dir="ltr">
                  {value ? new Date(value).toLocaleString() : t('analytics2.none')}
                </dd>
              </div>
            ))}
            <div className="flex items-center justify-between gap-3 border-t border-border pt-2.5">
              <dt className="text-muted-foreground">{t('analytics2.timeline.startDelay')}</dt>
              <dd className="font-medium">
                {startDelay === null
                  ? t('analytics2.none')
                  : startDelay >= 0
                    ? t('analytics2.timeline.late', { value: fmtDuration(startDelay) })
                    : t('analytics2.timeline.early', { value: fmtDuration(-startDelay) })}
              </dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('analytics2.timeline.duration')}</dt>
              <dd className="font-medium">{fmtDuration(a.timeline.duration_seconds)}</dd>
            </div>
          </dl>
        </Panel>

        {/* Health */}
        <Panel title={t('analytics2.health.title')} subtitle={t('analytics2.health.window', { days: a.health.retention_days })} icon={HeartPulse}>
          <div className="grid grid-cols-3 gap-2 text-center">
            <div className="border border-border p-2">
              <p className="text-lg font-bold tabular-nums text-destructive">{fmtNum(a.health.failure_count)}</p>
              <p className="text-[11px] text-muted-foreground">{t('analytics2.health.failures')}</p>
            </div>
            <div className="border border-border p-2">
              <p className="text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{fmtNum(a.health.recovery_count)}</p>
              <p className="text-[11px] text-muted-foreground">{t('analytics2.health.recoveries')}</p>
            </div>
            <div className="border border-border p-2">
              <p className="text-lg font-bold tabular-nums">{a.health.avg_latency_ms ?? '—'}</p>
              <p className="text-[11px] text-muted-foreground">{t('analytics2.health.avgLatency')}</p>
            </div>
          </div>
          {a.health.recent_events.length > 0 ? (
            <ul className="mt-3 space-y-1.5">
              {a.health.recent_events.slice(0, 8).map((e, i) => (
                <li key={i} className="flex items-center justify-between gap-2 text-xs">
                  <span className={cn('inline-flex items-center gap-1.5', e.status === 'failed' ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400')}>
                    <span className={cn('h-1.5 w-1.5', e.status === 'failed' ? 'bg-destructive' : 'bg-emerald-500')} />
                    {t(`analytics2.health.status.${e.status}`, { defaultValue: e.status })}
                    {e.reason ? <span className="text-muted-foreground">· {e.reason}</span> : null}
                  </span>
                  <span className="shrink-0 tabular-nums text-muted-foreground" dir="ltr">
                    {e.at ? new Date(e.at).toLocaleTimeString() : ''}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-3 text-center text-xs text-muted-foreground">{t('analytics2.health.empty')}</p>
          )}
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Moderation */}
        <Panel title={t('analytics2.moderation.title')} icon={ShieldAlert}>
          <div className="grid grid-cols-3 gap-2 text-center">
            {MOD_KEYS.map((k) => (
              <div key={k} className="border border-border p-2">
                <p className="text-lg font-bold tabular-nums">{fmtNum(a.moderation[k])}</p>
                <p className="text-[11px] text-muted-foreground">{t(`analytics2.moderation.${k}`)}</p>
              </div>
            ))}
          </div>
          {a.moderation.recent_events.length > 0 ? (
            <ul className="mt-3 space-y-1.5">
              {a.moderation.recent_events.slice(0, 8).map((e, i) => (
                <li key={i} className="flex items-center justify-between gap-2 text-xs">
                  <span className="inline-flex items-center gap-1.5">
                    <Ban className="h-3 w-3 text-muted-foreground" />
                    {t(`analytics2.modEvent.${e.event}`, { defaultValue: e.event })}
                    {e.member ? <span className="text-muted-foreground" dir="ltr">· {e.member}</span> : null}
                  </span>
                  <span className="shrink-0 tabular-nums text-muted-foreground" dir="ltr">
                    {e.at ? new Date(e.at).toLocaleString() : ''}
                  </span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="mt-3 text-center text-xs text-muted-foreground">{t('analytics2.moderation.empty')}</p>
          )}
        </Panel>

        {/* Notifications */}
        <Panel title={t('analytics2.notifications.title')} icon={Bell}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('analytics2.notifications.reminders')}</dt>
              <dd className="font-medium tabular-nums">{fmtNum(a.notifications.reminder_subscribers)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('analytics2.notifications.global')}</dt>
              <dd className="font-medium tabular-nums">{fmtNum(a.notifications.global_subscribers)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('analytics2.notifications.liveNotified')}</dt>
              <dd className="font-medium">{a.notifications.live_notified_at ? t('analytics2.yes') : t('analytics2.no')}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('analytics2.notifications.reminderSent')}</dt>
              <dd className="font-medium">{a.notifications.reminder_dispatched_at ? t('analytics2.yes') : t('analytics2.no')}</dd>
            </div>
          </dl>
          <div className="mt-3">
            <DeferredNotice title={t('analytics2.delivery.title')} note={t('analytics2.delivery.note')} />
          </div>
        </Panel>
      </div>
    </div>
  );
}
