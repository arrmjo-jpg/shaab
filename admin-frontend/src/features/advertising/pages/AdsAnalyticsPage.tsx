import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  BarChart3,
  Eye,
  Image as ImageIcon,
  LayoutTemplate,
  Megaphone,
  MousePointerClick,
  Percent,
  Radio,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
  BarRow,
  MetricCard,
  Panel,
  RangeFilter,
  TrendChart,
  fmtNum,
  type RangeValue,
} from '@/components/analytics/AnalyticsKit';
import { useAdAnalytics } from '../hooks';
import type { AdAnalyticsTopRow } from '@/types/advertising.types';

type TrendMetric = 'impressions' | 'clicks';

function TopList({ rows, total, emptyLabel }: { rows: AdAnalyticsTopRow[]; total: number; emptyLabel: string }) {
  if (rows.length === 0) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{emptyLabel}</p>;
  }
  return (
    <div className="space-y-3">
      {rows.map((r) => (
        <BarRow key={r.id} label={r.name} value={r.impressions} total={total} />
      ))}
    </div>
  );
}

export default function AdsAnalyticsPage() {
  const { t } = useTranslation('advertising');
  const [range, setRange] = useState<RangeValue>({ range: '7d' });
  const [metric, setMetric] = useState<TrendMetric>('impressions');

  const q = useAdAnalytics(range.range, range.from, range.to);
  const data = q.data;

  const rangeLabels: Record<string, string> = {
    '24h': t('analytics.range.24h'),
    '7d': t('analytics.range.7d'),
    '30d': t('analytics.range.30d'),
    custom: t('analytics.range.custom'),
  };

  const Header = (
    <header className="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold">{t('analytics.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('analytics.subtitle')}</p>
      </div>
      <RangeFilter value={range} onChange={setRange} labels={rangeLabels} />
    </header>
  );

  if (q.isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('analytics.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('analytics.retry')}
          </Button>
        </div>
      </div>
    );
  }

  if (q.isLoading || !data) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <Skeleton className="h-56 w-full" />
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-56 w-full" />
          <Skeleton className="h-56 w-full" />
        </div>
      </div>
    );
  }

  const totalImp = data.totals.impressions;
  const trendPoints = data.trend.points.map((p) => ({
    label: p.date.slice(5),
    value: metric === 'impressions' ? p.impressions : p.clicks,
  }));
  const channels = [
    { key: 'direct', value: data.channels.direct },
    { key: 'internal', value: data.channels.internal },
    { key: 'search', value: data.channels.search },
    { key: 'social', value: data.channels.social },
    { key: 'referral', value: data.channels.referral },
  ];
  const channelTotal = channels.reduce((a, c) => a + c.value, 0);

  const metricToggle = (
    <div className="inline-flex border border-border">
      {(['impressions', 'clicks'] as TrendMetric[]).map((m) => (
        <button
          key={m}
          type="button"
          onClick={() => setMetric(m)}
          className={cn(
            'px-3 py-1 text-xs font-medium transition-colors',
            metric === m ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
          )}
        >
          {t(`analytics.metric.${m}`)}
        </button>
      ))}
    </div>
  );

  return (
    <div className="space-y-6">
      {Header}

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <MetricCard label={t('analytics.kpi.impressions')} value={fmtNum(data.totals.impressions)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('analytics.kpi.clicks')} value={fmtNum(data.totals.clicks)} icon={MousePointerClick} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('analytics.kpi.ctr')} value={`${data.totals.ctr.toFixed(2)}%`} icon={Percent} tone="text-amber-600 dark:text-amber-400" />
      </div>

      <Panel title={t('analytics.trend.title')} subtitle={t('analytics.trend.subtitle')} icon={BarChart3} action={metricToggle}>
        <TrendChart
          points={trendPoints}
          color={metric === 'clicks' ? 'bg-emerald-500' : 'bg-primary'}
          emptyLabel={t('analytics.empty')}
        />
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title={t('analytics.topCampaigns')} icon={Megaphone}>
          <TopList rows={data.top_campaigns} total={totalImp} emptyLabel={t('analytics.empty')} />
        </Panel>
        <Panel title={t('analytics.topCreatives')} icon={ImageIcon}>
          <TopList rows={data.top_creatives} total={totalImp} emptyLabel={t('analytics.empty')} />
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title={t('analytics.topZones')} icon={LayoutTemplate}>
          <TopList rows={data.top_zones} total={totalImp} emptyLabel={t('analytics.empty')} />
        </Panel>
        <Panel title={t('analytics.channels')} subtitle={t('analytics.channelsSubtitle')} icon={Radio}>
          {channelTotal === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.empty')}</p>
          ) : (
            <div className="space-y-3">
              {channels.map((c) => (
                <BarRow key={c.key} label={t(`analytics.channel.${c.key}`)} value={c.value} total={channelTotal} />
              ))}
            </div>
          )}
        </Panel>
      </div>
    </div>
  );
}
