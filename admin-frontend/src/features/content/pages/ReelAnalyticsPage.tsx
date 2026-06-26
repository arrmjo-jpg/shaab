import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Activity,
  ArrowRight,
  BarChart3,
  Bookmark,
  Clock,
  Eye,
  Gauge,
  Globe,
  Languages,
  ThumbsDown,
  ThumbsUp,
  TrendingUp,
  Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';
import {
  BarRow,
  DeferredNotice,
  fmtDuration,
  fmtNum,
  MetricCard,
  Panel,
  RangeFilter,
  TrendChart,
  type RangeValue,
} from '@/components/analytics/AnalyticsKit';
import { useReelEntityAnalytics } from '../reels.hooks';

type TrendMetric = 'views' | 'likes' | 'dislikes' | 'favorites';
const TREND_METRICS: TrendMetric[] = ['views', 'likes', 'dislikes', 'favorites'];
const METRIC_COLOR: Record<TrendMetric, string> = {
  views: 'bg-sky-500',
  likes: 'bg-emerald-500',
  dislikes: 'bg-destructive',
  favorites: 'bg-amber-500',
};
const CHANNELS = ['direct', 'internal', 'search', 'social', 'referral'] as const;

/**
 * تحليلات ريل واحد (v1) — مقاييس حقيقية فقط. التفاعل تراكميّ؛ الاتجاهات/الزيارات
 * «إلى-الأمام»؛ مقاييس المشاهدة/الاكتشاف قصيرة-الشكل مؤجّلة بصدق.
 */
export default function ReelAnalyticsPage() {
  const { t } = useTranslation('content');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const reelId = id ? Number(id) : null;

  const [range, setRange] = useState<RangeValue>({ range: '30d' });
  const [metric, setMetric] = useState<TrendMetric>('views');
  const q = useReelEntityAnalytics(reelId, range.range, range.from, range.to);

  const rangeLabels: Record<string, string> = {
    '24h': t('reelAnalytics.range.24h'),
    '7d': t('reelAnalytics.range.7d'),
    '30d': t('reelAnalytics.range.30d'),
    custom: t('reelAnalytics.range.custom'),
  };

  const Header = (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="flex items-start gap-3">
        <Button variant="ghost" size="icon" onClick={() => navigate(paths.reels)}>
          <ArrowRight className="h-4 w-4" />
        </Button>
        <div>
          <p className="text-xs font-medium text-muted-foreground">{t('reelAnalytics.title')}</p>
          <h1 className="text-2xl font-bold">{q.data?.entity.title ?? t('reelAnalytics.loadingTitle')}</h1>
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
          <span className="text-destructive">{t('reelAnalytics.error')}</span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('reelAnalytics.retry')}
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
      </div>
    );
  }

  const a = q.data;
  const p = a.performance;
  const trendPoints = a.trend.points.map((pt) => ({ label: pt.date.slice(5), value: pt[metric] }));

  return (
    <div className="space-y-6">
      {Header}

      {/* Engagement KPIs (cumulative) */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('reelAnalytics.metric.views')} value={fmtNum(a.engagement.views)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('reelAnalytics.uniqueReactors')} value={fmtNum(a.engagement.unique_reactors)} icon={Users} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('reelAnalytics.metric.likes')} value={fmtNum(a.engagement.likes)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('reelAnalytics.metric.dislikes')} value={fmtNum(a.engagement.dislikes)} icon={ThumbsDown} tone="text-destructive" />
        <MetricCard label={t('reelAnalytics.metric.favorites')} value={fmtNum(a.engagement.favorites)} icon={Bookmark} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('reelAnalytics.engagementRate')} value={`${a.engagement.engagement_rate}%`} icon={Activity} tone="text-primary" />
      </div>

      {/* Performance */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <MetricCard label={t('reelAnalytics.perf.trendingScore')} value={fmtNum(p.trending_score)} icon={TrendingUp} tone="text-primary" />
        <MetricCard label={t('reelAnalytics.perf.velocity')} value={fmtNum(Math.round(p.velocity_per_day))} icon={Gauge} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard
          label={t('reelAnalytics.perf.momentum')}
          value={p.momentum_pct === null ? '—' : `${p.momentum_pct > 0 ? '+' : ''}${p.momentum_pct}%`}
          icon={Activity}
          tone={p.momentum_pct !== null && p.momentum_pct < 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}
        />
        <MetricCard
          label={t('reelAnalytics.perf.vsBaseline')}
          value={p.baseline.vs_baseline_pct === null ? '—' : `${p.baseline.vs_baseline_pct > 0 ? '+' : ''}${p.baseline.vs_baseline_pct}%`}
          icon={Users}
          tone={p.baseline.vs_baseline_pct !== null && p.baseline.vs_baseline_pct < 0 ? 'text-destructive' : 'text-emerald-600 dark:text-emerald-400'}
        />
      </div>

      {/* Trend over time (forward-only) */}
      <Panel title={t('reelAnalytics.trend.title')} subtitle={t('reelAnalytics.forwardOnly')} icon={BarChart3}>
        <div className="mb-3 flex flex-wrap items-center gap-1.5">
          {TREND_METRICS.map((m) => (
            <button
              key={m}
              type="button"
              onClick={() => setMetric(m)}
              className={cn(
                'px-2.5 py-1 text-xs font-medium transition-colors',
                metric === m ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-muted/70',
              )}
            >
              {t(`reelAnalytics.metric.${m}`)}
            </button>
          ))}
          <span className="ms-auto text-xs tabular-nums text-muted-foreground">
            {t('reelAnalytics.trend.rangeTotal', { value: fmtNum(a.trend.totals[metric]) })}
          </span>
        </div>
        <TrendChart points={trendPoints} color={METRIC_COLOR[metric]} emptyLabel={t('reelAnalytics.trend.empty')} />
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Traffic sources (coarse, forward-only) */}
        <Panel title={t('reelAnalytics.traffic.title')} subtitle={t('reelAnalytics.traffic.subtitle')} icon={Globe}>
          {a.traffic.total === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('reelAnalytics.traffic.empty')}</p>
          ) : (
            <div className="space-y-3">
              {CHANNELS.map((c) => (
                <BarRow key={c} label={t(`reelAnalytics.channel.${c}`)} value={a.traffic.channels[c]} total={a.traffic.total} />
              ))}
            </div>
          )}
        </Panel>

        {/* Publishing */}
        <Panel title={t('reelAnalytics.publishing.title')} icon={Clock}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('reelAnalytics.publishing.status')}</dt>
              <dd className="font-medium">{t(`reels.status.${a.publishing.status}`, { defaultValue: a.publishing.status })}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('reelAnalytics.publishing.featured')}</dt>
              <dd className="font-medium">{a.publishing.is_featured ? t('reelAnalytics.yes') : t('reelAnalytics.no')}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('reelAnalytics.publishing.duration')}</dt>
              <dd className="font-medium">{fmtDuration(a.entity.duration_seconds)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('reelAnalytics.publishing.publishedAt')}</dt>
              <dd className="font-medium tabular-nums" dir="ltr">
                {a.publishing.published_at ? new Date(a.publishing.published_at).toLocaleString() : t('reelAnalytics.none')}
              </dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="flex items-center gap-1.5 text-muted-foreground"><Languages className="h-3.5 w-3.5" />{t('reelAnalytics.publishing.language')}</dt>
              <dd className="font-medium uppercase">{a.publishing.locale}</dd>
            </div>
            {a.publishing.translations.length > 0 ? (
              <div className="border-t border-border pt-2.5">
                <dt className="mb-1.5 text-xs text-muted-foreground">{t('reelAnalytics.publishing.translations')}</dt>
                <ul className="space-y-1">
                  {a.publishing.translations.map((tr) => (
                    <li key={tr.id} className="flex items-center gap-2 text-sm">
                      <span className="bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase">{tr.locale}</span>
                      <span className="truncate">{tr.title}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </dl>
        </Panel>
      </div>

      {/* Deferred — short-form watch + discovery telemetry */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title={t('reelAnalytics.watch.title')} icon={Activity}>
          <DeferredNotice title={t('reelAnalytics.watch.deferredTitle')} note={t('reelAnalytics.watch.deferredNote')} />
        </Panel>
        <Panel title={t('reelAnalytics.discovery.title')} icon={TrendingUp}>
          <DeferredNotice title={t('reelAnalytics.discovery.deferredTitle')} note={t('reelAnalytics.discovery.deferredNote')} />
        </Panel>
      </div>
    </div>
  );
}
