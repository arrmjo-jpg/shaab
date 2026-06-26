import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { BarChart3, CheckCircle2, ListChecks, ListOrdered, Power, Trophy, Unlock, Users, Vote } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { paths } from '@/router/paths';
import { fmtNum, MetricCard, Panel, TrendChart } from '@/components/analytics/AnalyticsKit';
import { usePollAnalytics } from '../hooks';
import type { PollState } from '@/types/polls.types';

const STATE_TONE: Record<PollState, string> = {
  open: 'text-emerald-600 dark:text-emerald-400',
  scheduled: 'text-sky-600 dark:text-sky-400',
  closed: 'text-muted-foreground',
  inactive: 'text-muted-foreground',
};

/**
 * تحليلات أسطول الاستطلاعات (مدى كامل) — مؤشّرات + توزيع الحالات + لوحة صدارة
 * (مصوّتون فريدون) + منحنى المشاركة الحديثة. بيانات حقيقية فقط؛ تكمّل صفحة
 * الاستطلاع المفرد (النطاق الزمني هناك). لا زيارات/قنوات للاستطلاعات.
 */
export default function PollAnalyticsOverviewPage() {
  const { t } = useTranslation('polls');
  const navigate = useNavigate();
  const q = usePollAnalytics();

  const Header = (
    <header>
      <h1 className="text-2xl font-bold">{t('pollAnalytics.overview.title')}</h1>
      <p className="text-sm text-muted-foreground">{t('pollAnalytics.overview.subtitle')}</p>
    </header>
  );

  if (q.isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="text-destructive">{t('pollAnalytics.error')}</span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('pollAnalytics.retry')}
          </Button>
        </div>
      </div>
    );
  }

  if (q.isLoading || !q.data) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  const a = q.data;
  const kpis = a.kpis;
  const sb = a.status_breakdown;
  const statusTotal = sb.open + sb.scheduled + sb.closed + sb.inactive;
  const trendPoints = a.recent_participation.points.map((pt) => ({ label: pt.date.slice(5), value: pt.votes }));

  const statusRows: Array<{ key: keyof typeof sb; tone: string }> = [
    { key: 'open', tone: 'bg-emerald-500' },
    { key: 'scheduled', tone: 'bg-sky-500' },
    { key: 'closed', tone: 'bg-muted-foreground' },
    { key: 'inactive', tone: 'bg-muted-foreground' },
  ];

  return (
    <div className="space-y-6">
      {Header}

      {/* All-time note */}
      <div className="flex items-center gap-2 border border-border bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
        <BarChart3 className="h-4 w-4 shrink-0" />
        {t('pollAnalytics.overview.allTime')}
      </div>

      {/* Fleet KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <MetricCard label={t('pollAnalytics.kpi.totalPolls')} value={fmtNum(kpis.total_polls)} icon={Vote} tone="text-primary" />
        <MetricCard label={t('pollAnalytics.kpi.activePolls')} value={fmtNum(kpis.active_polls)} icon={Power} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('pollAnalytics.kpi.openPolls')} value={fmtNum(kpis.open_polls)} icon={Unlock} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('pollAnalytics.kpi.totalVotes')} value={fmtNum(kpis.total_votes)} icon={Users} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('pollAnalytics.kpi.totalSelections')} value={fmtNum(kpis.total_selections)} icon={ListChecks} tone="text-amber-600 dark:text-amber-400" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Status breakdown */}
        <Panel title={t('pollAnalytics.status.title')} subtitle={t('pollAnalytics.status.subtitle')} icon={CheckCircle2}>
          {statusTotal === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('pollAnalytics.status.empty')}</p>
          ) : (
            <div className="space-y-3">
              {statusRows.map((row) => {
                const value = sb[row.key];
                const pct = statusTotal > 0 ? Math.round((value / statusTotal) * 100) : 0;
                return (
                  <div key={row.key} className="space-y-1">
                    <div className="flex items-center justify-between gap-2 text-xs">
                      <span className="truncate text-muted-foreground">{t(`pollState.${row.key}`)}</span>
                      <span className="shrink-0 font-medium tabular-nums">
                        {fmtNum(value)} <span className="text-muted-foreground">({pct}%)</span>
                      </span>
                    </div>
                    <div className="h-2 w-full bg-muted">
                      <div className={row.tone} style={{ width: `${pct}%`, height: '100%' }} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Panel>

        {/* Leaderboard — top polls by unique voters */}
        <Panel title={t('pollAnalytics.leaderboard.title')} subtitle={t('pollAnalytics.leaderboard.subtitle')} icon={Trophy}>
          {a.top_polls.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('pollAnalytics.leaderboard.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {a.top_polls.map((p, i) => (
                <li key={p.id}>
                  <button
                    type="button"
                    onClick={() => navigate(paths.pollAnalytics.replace(':id', String(p.id)))}
                    className="flex w-full items-center gap-3 text-start text-sm transition-colors hover:text-primary"
                  >
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-muted text-xs font-semibold tabular-nums text-muted-foreground">
                      {i + 1}
                    </span>
                    <span className="min-w-0 flex-1 truncate font-medium">
                      {p.question}
                      <span className={`ms-2 text-xs font-normal ${STATE_TONE[p.state]}`}>{t(`pollState.${p.state}`)}</span>
                    </span>
                    <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                      {fmtNum(p.unique_voters)}
                    </span>
                  </button>
                </li>
              ))}
            </ol>
          )}
        </Panel>
      </div>

      {/* Recent participation */}
      <Panel
        title={t('pollAnalytics.recent.title')}
        subtitle={t('pollAnalytics.recent.subtitle', { days: a.recent_participation.days })}
        icon={ListOrdered}
        action={
          <span className="text-xs tabular-nums text-muted-foreground">
            {t('pollAnalytics.recent.total', { value: fmtNum(a.recent_participation.totals.votes) })}
          </span>
        }
      >
        <TrendChart points={trendPoints} color="bg-primary" emptyLabel={t('pollAnalytics.recent.empty')} />
      </Panel>
    </div>
  );
}
