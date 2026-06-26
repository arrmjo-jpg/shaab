import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  Ban,
  BellRing,
  CalendarClock,
  CheckCircle2,
  DoorClosed,
  DoorOpen,
  Eye,
  Radio,
  RadioTower,
  ShieldAlert,
  Square,
  Tv,
  Users,
  WifiOff,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { ErrorState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useBroadcastDashboard, useBroadcastLifecycle, useBroadcastModeration } from '../hooks';
import { MetricCard, Panel } from '../components/StatPrimitives';
import type {
  BroadcastChannelOverview,
  BroadcastDashboardLive,
  BroadcastHealth,
  BroadcastStatus,
} from '@/types/broadcast.types';

function fmt(n: number, locale: string): string {
  return n.toLocaleString(locale);
}

function fmtTime(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

function fmtDateTime(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/** عدّ تنازليّ مبسّط حتى وقت مجدول (أو "الآن" إن مرّ). */
function countdown(iso: string, locale: string, soonLabel: string): string {
  const diff = new Date(iso).getTime() - Date.now();
  if (diff <= 0) return soonLabel;
  const mins = Math.round(diff / 60000);
  if (mins < 60) return `${fmt(mins, locale)}m`;
  const hours = Math.floor(mins / 60);
  const rem = mins % 60;
  return `${fmt(hours, locale)}h ${fmt(rem, locale)}m`;
}

const HEALTH_TONE: Record<string, 'success' | 'muted' | 'destructive'> = {
  healthy: 'success',
  online: 'success',
  up: 'success',
  degraded: 'muted',
  unknown: 'muted',
  down: 'destructive',
  failed: 'destructive',
  offline: 'destructive',
};

function HealthBadge({ health }: { health: BroadcastHealth }) {
  const { t } = useTranslation('broadcast');
  const status = health.status;
  if (!status) return <Badge variant="muted">{t('health.unknown')}</Badge>;
  return <Badge variant={HEALTH_TONE[status] ?? 'muted'}>{t(`health.status.${status}`, { defaultValue: status })}</Badge>;
}

const STATUS_KEYS: BroadcastStatus[] = ['live', 'scheduled', 'offline', 'ended', 'failed', 'draft', 'archived'];
const STATUS_TONE: Record<BroadcastStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  scheduled: 'muted',
  live: 'success',
  offline: 'muted',
  ended: 'muted',
  failed: 'destructive',
  archived: 'muted',
};

export default function CommandCenterPage() {
  const { t, i18n } = useTranslation('broadcast');
  const locale = i18n.language;
  const { hasPermission } = useAuth();
  const { confirm } = useToast();
  const { data, isLoading, isError, refetch } = useBroadcastDashboard();
  const lifecycle = useBroadcastLifecycle();
  const moderation = useBroadcastModeration();

  const canControl = hasPermission('broadcasts.control');
  const canAudience = hasPermission('broadcasts.audience_control');
  const canEmergency = hasPermission('broadcasts.emergency_shutdown');

  const onForceOffline = async (b: BroadcastDashboardLive) => {
    if (
      await confirm({
        title: t('confirm.offlineTitle'),
        text: t('confirm.offlineText', { title: b.title }),
        confirmText: t('action.offline'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      lifecycle.mutate({ id: b.id, action: 'offline' });
  };
  const onEnd = async (b: BroadcastDashboardLive) => {
    if (
      await confirm({
        title: t('confirm.endTitle'),
        text: t('confirm.endText', { title: b.title }),
        confirmText: t('action.end'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      lifecycle.mutate({ id: b.id, action: 'end' });
  };
  const onToggleAudience = async (b: BroadcastDashboardLive) => {
    const action = b.audience_closed ? 'reopen' : 'close';
    if (
      await confirm({
        title: b.audience_closed ? t('confirm.reopenTitle') : t('confirm.closeTitle'),
        text: b.audience_closed ? t('confirm.reopenText', { title: b.title }) : t('confirm.closeText', { title: b.title }),
        confirmText: b.audience_closed ? t('moderation.reopen') : t('moderation.close'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      moderation.mutate({ id: b.id, action });
  };
  const onEmergency = async (b: BroadcastDashboardLive) => {
    if (
      await confirm({
        title: t('confirm.emergencyTitle'),
        text: t('confirm.emergencyText', { title: b.title }),
        confirmText: t('moderation.emergency'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      moderation.mutate({ id: b.id, action: 'emergency-shutdown' });
  };

  if (isError) {
    return <ErrorState onRetry={() => void refetch()} />;
  }

  if (isLoading || !data) {
    return (
      <div className="space-y-6">
        <header>
          <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
        </header>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-48 w-full" />
          <Skeleton className="h-48 w-full" />
        </div>
      </div>
    );
  }

  const { totals, status_counts, live, scheduled_today, channels, health_alerts, audience, notifications } = data;
  const statusTotal = STATUS_KEYS.reduce((a, k) => a + (status_counts[k] ?? 0), 0) || 1;

  const channelRow = (label: string, icon: typeof Tv, o: BroadcastChannelOverview) => (
    <Panel title={label} icon={icon}>
      <div className="grid grid-cols-4 gap-2 text-center">
        <div className="border border-border p-3">
          <p className="text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{fmt(o.live, locale)}</p>
          <p className="text-xs text-muted-foreground">{t('status.live')}</p>
        </div>
        <div className="border border-border p-3">
          <p className="text-xl font-bold tabular-nums text-muted-foreground">{fmt(o.offline, locale)}</p>
          <p className="text-xs text-muted-foreground">{t('status.offline')}</p>
        </div>
        <div className={cn('border p-3', o.failed > 0 ? 'border-destructive' : 'border-border')}>
          <p className={cn('text-xl font-bold tabular-nums', o.failed > 0 ? 'text-destructive' : 'text-muted-foreground')}>
            {fmt(o.failed, locale)}
          </p>
          <p className="text-xs text-muted-foreground">{t('status.failed')}</p>
        </div>
        <div className="border border-border p-3">
          <p className="text-xl font-bold tabular-nums">{fmt(o.total, locale)}</p>
          <p className="text-xs text-muted-foreground">{t('dashboard.channels.total')}</p>
        </div>
      </div>
    </Panel>
  );

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
      </header>

      {/* KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('dashboard.kpi.live')} value={fmt(totals.live, locale)} icon={RadioTower} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('dashboard.kpi.liveViewers')} value={fmt(totals.live_viewers, locale)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('dashboard.kpi.scheduled')} value={fmt(totals.scheduled, locale)} icon={CalendarClock} />
        <MetricCard label={t('dashboard.kpi.failed')} value={fmt(totals.failed, locale)} icon={AlertTriangle} tone={totals.failed > 0 ? 'text-destructive' : 'text-muted-foreground'} />
        <MetricCard label={t('dashboard.kpi.globalSubs')} value={fmt(notifications.global_subscribers, locale)} icon={BellRing} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('dashboard.kpi.closedAudiences')} value={fmt(audience.closed.length, locale)} icon={DoorClosed} tone={audience.closed.length > 0 ? 'text-destructive' : 'text-muted-foreground'} />
      </div>

      {/* Live Now */}
      <Panel title={t('dashboard.liveNow.title')} subtitle={t('dashboard.liveNow.subtitle')} icon={RadioTower}>
        {live.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('dashboard.liveNow.empty')}</p>
        ) : (
          <div className="grid gap-3 lg:grid-cols-2">
            {live.map((b) => (
              <div key={b.id} className="space-y-3 border border-border bg-background p-3">
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="flex items-center gap-1.5">
                      <Badge variant="success">{t('status.live')}</Badge>
                      <Badge variant="muted">{t(`kind.${b.kind}`)}</Badge>
                      {b.is_featured ? <Badge variant="default">{t('dashboard.liveNow.featured')}</Badge> : null}
                    </div>
                    <p className="mt-1.5 truncate font-medium">{b.title}</p>
                  </div>
                  <div className="shrink-0 text-end">
                    <p className="flex items-center justify-end gap-1 text-sm font-bold tabular-nums">
                      <Eye className="h-3.5 w-3.5 text-muted-foreground" />
                      {fmt(b.viewer_count, locale)}
                    </p>
                    <p className="text-[11px] text-muted-foreground">{t('dashboard.liveNow.since', { time: fmtTime(b.started_at, locale) })}</p>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                  <HealthBadge health={b.health} />
                  {b.audience_closed ? (
                    <Badge variant="destructive" className="gap-1">
                      <DoorClosed className="h-3 w-3" />
                      {t('dashboard.liveNow.audienceClosed')}
                    </Badge>
                  ) : null}
                </div>

                {canControl || canAudience || canEmergency ? (
                  <div className="flex flex-wrap gap-1.5 border-t border-border pt-2">
                    {canControl ? (
                      <Button variant="outline" size="sm" onClick={() => void onForceOffline(b)} disabled={lifecycle.isPending}>
                        <WifiOff className="h-4 w-4" />
                        {t('action.offline')}
                      </Button>
                    ) : null}
                    {canControl ? (
                      <Button variant="outline" size="sm" onClick={() => void onEnd(b)} disabled={lifecycle.isPending}>
                        <Square className="h-4 w-4" />
                        {t('action.end')}
                      </Button>
                    ) : null}
                    {canAudience ? (
                      <Button variant="outline" size="sm" onClick={() => void onToggleAudience(b)} disabled={moderation.isPending}>
                        {b.audience_closed ? <DoorOpen className="h-4 w-4" /> : <DoorClosed className="h-4 w-4" />}
                        {b.audience_closed ? t('moderation.reopen') : t('moderation.close')}
                      </Button>
                    ) : null}
                    {canEmergency ? (
                      <Button variant="outline" size="sm" className="text-destructive" onClick={() => void onEmergency(b)} disabled={moderation.isPending}>
                        <ShieldAlert className="h-4 w-4" />
                        {t('moderation.emergency')}
                      </Button>
                    ) : null}
                  </div>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Scheduled today */}
        <Panel title={t('dashboard.scheduledToday.title')} subtitle={t('dashboard.scheduledToday.subtitle')} icon={CalendarClock}>
          {scheduled_today.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('dashboard.scheduledToday.empty')}</p>
          ) : (
            <ul className="space-y-2">
              {scheduled_today.map((s) => (
                <li key={s.id} className="flex items-center gap-3 border border-border p-3 text-sm">
                  <span className="flex h-10 w-16 shrink-0 flex-col items-center justify-center bg-muted">
                    <span className="text-sm font-bold tabular-nums">{countdown(s.scheduled_at, locale, t('dashboard.scheduledToday.soon'))}</span>
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-1.5">
                      <Badge variant="muted">{t(`kind.${s.kind}`)}</Badge>
                      <span className="truncate font-medium">{s.title}</span>
                    </span>
                    <span className="mt-0.5 block text-xs text-muted-foreground">{fmtDateTime(s.scheduled_at, locale)}</span>
                  </span>
                  <span className="shrink-0 text-end">
                    <span className="flex items-center justify-end gap-1 text-xs tabular-nums text-muted-foreground">
                      <BellRing className="h-3.5 w-3.5" />
                      {fmt(s.reminder_subscribers, locale)}
                    </span>
                    {s.reminder_dispatched ? (
                      <Badge variant="success" className="mt-1">
                        <CheckCircle2 className="h-3 w-3" />
                        {t('dashboard.scheduledToday.dispatched')}
                      </Badge>
                    ) : (
                      <Badge variant="muted" className="mt-1">{t('dashboard.scheduledToday.pending')}</Badge>
                    )}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        {/* Status mix */}
        <Panel title={t('dashboard.statusMix.title')} subtitle={t('dashboard.statusMix.subtitle')}>
          <div className="space-y-3">
            {STATUS_KEYS.map((k) => {
              const v = status_counts[k] ?? 0;
              const pct = Math.round((v / statusTotal) * 100);
              return (
                <div key={k}>
                  <div className="mb-1 flex items-center justify-between text-xs">
                    <span className="flex items-center gap-1.5 font-medium">
                      <Badge variant={STATUS_TONE[k]}>{t(`status.${k}`)}</Badge>
                    </span>
                    <span className="tabular-nums text-muted-foreground">{fmt(v, locale)}</span>
                  </div>
                  <div className="h-2 w-full overflow-hidden bg-muted">
                    <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </Panel>
      </div>

      {/* Channels */}
      <div className="grid gap-4 lg:grid-cols-2">
        {channelRow(t('dashboard.channels.tv'), Tv, channels.tv)}
        {channelRow(t('dashboard.channels.radio'), Radio, channels.radio)}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Health alerts */}
        <Panel title={t('dashboard.healthAlerts.title')} subtitle={t('dashboard.healthAlerts.subtitle')} icon={AlertTriangle}>
          {health_alerts.length === 0 ? (
            <div className="flex items-center gap-2 py-6 text-sm text-emerald-600 dark:text-emerald-400">
              <CheckCircle2 className="h-4 w-4" />
              {t('dashboard.healthAlerts.empty')}
            </div>
          ) : (
            <ul className="space-y-2">
              {health_alerts.map((a) => (
                <li key={a.id} className="border border-destructive/40 bg-destructive/5 p-3 text-sm">
                  <div className="flex items-center gap-1.5">
                    <Badge variant="destructive">{t('status.failed')}</Badge>
                    <Badge variant="muted">{t(`kind.${a.kind}`)}</Badge>
                    <span className="truncate font-medium">{a.title}</span>
                  </div>
                  {a.message ? <p className="mt-1 text-xs text-muted-foreground">{a.message}</p> : null}
                  <p className="mt-1 flex items-center gap-1 text-[11px] text-muted-foreground">
                    <CalendarClock className="h-3 w-3" />
                    {t('dashboard.healthAlerts.checkedAt', { time: fmtDateTime(a.checked_at, locale) })}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </Panel>

        {/* Audience + notifications */}
        <div className="space-y-4">
          <Panel title={t('dashboard.audience.title')} subtitle={t('dashboard.audience.subtitle')} icon={Users}>
            {audience.closed.length === 0 ? (
              <p className="py-4 text-center text-sm text-muted-foreground">{t('dashboard.audience.empty')}</p>
            ) : (
              <ul className="space-y-2">
                {audience.closed.map((c) => (
                  <li key={c.id} className="flex items-center gap-2 border border-border p-2 text-sm">
                    <DoorClosed className="h-4 w-4 shrink-0 text-destructive" />
                    <span className="truncate font-medium">{c.title}</span>
                    <Badge variant="destructive" className="ms-auto">{t('dashboard.audience.closed')}</Badge>
                  </li>
                ))}
              </ul>
            )}
            <p className="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground">
              <Ban className="mt-0.5 h-3.5 w-3.5 shrink-0" />
              {t('dashboard.audience.emergencyNote')}
            </p>
          </Panel>

          <Panel title={t('dashboard.notifications.title')} icon={BellRing}>
            <div className="grid grid-cols-2 gap-3 text-center">
              <div className="border border-border p-3">
                <p className="text-2xl font-bold tabular-nums">{fmt(notifications.global_subscribers, locale)}</p>
                <p className="text-xs text-muted-foreground">{t('dashboard.notifications.globalSubs')}</p>
              </div>
              <div className="border border-border p-3">
                <p className="text-2xl font-bold tabular-nums">{fmt(notifications.upcoming_with_reminders, locale)}</p>
                <p className="text-xs text-muted-foreground">{t('dashboard.notifications.upcomingReminders')}</p>
              </div>
            </div>
          </Panel>
        </div>
      </div>
    </div>
  );
}
