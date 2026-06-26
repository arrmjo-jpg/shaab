import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, BarChart3, Calculator, Clock, ListChecks, ListOrdered, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { paths } from '@/router/paths';
import {
  BarRow,
  fmtNum,
  MetricCard,
  Panel,
  RangeFilter,
  TrendChart,
  type RangeValue,
} from '@/components/analytics/AnalyticsKit';
import { usePollEntityAnalytics } from '../hooks';
import type { PollState } from '@/types/polls.types';

const STATE_TONE: Record<PollState, string> = {
  open: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
  scheduled: 'bg-sky-500/12 text-sky-600 dark:text-sky-400',
  closed: 'bg-muted text-muted-foreground',
  inactive: 'bg-muted text-muted-foreground',
};

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

/**
 * تحليلات استطلاع واحد (سياقيّة) — مقاييس حقيقية فقط. المصوّتون الفريدون رقم دقيق
 * (لا تقريب)، ويُفصَل عن إجمالي الاختيارات (يختلفان في الاستطلاعات متعدّدة الاختيار).
 * منحنى المشاركة كامل ضمن النافذة — لا «إلى-الأمام» ولا زيارات/قنوات.
 */
export default function PollAnalyticsPage() {
  const { t, i18n } = useTranslation('polls');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const pollId = id ? Number(id) : null;

  const [range, setRange] = useState<RangeValue>({ range: '30d' });
  const q = usePollEntityAnalytics(pollId, range.range, range.from, range.to);

  const rangeLabels: Record<string, string> = {
    '24h': t('pollAnalytics.range.24h'),
    '7d': t('pollAnalytics.range.7d'),
    '30d': t('pollAnalytics.range.30d'),
    custom: t('pollAnalytics.range.custom'),
  };

  const Header = (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="flex items-start gap-3">
        <Button variant="ghost" size="icon" onClick={() => navigate(paths.polls)}>
          <ArrowRight className="h-4 w-4" />
        </Button>
        <div>
          <p className="text-xs font-medium text-muted-foreground">{t('pollAnalytics.title')}</p>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold">{q.data?.entity.question ?? t('pollAnalytics.loadingTitle')}</h1>
            {q.data ? (
              <span className={`px-2 py-0.5 text-xs font-medium ${STATE_TONE[q.data.entity.state]}`}>
                {t(`pollState.${q.data.entity.state}`)}
              </span>
            ) : null}
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
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  const a = q.data;
  const p = a.participation;
  const trendPoints = a.trend.points.map((pt) => ({ label: pt.date.slice(5), value: pt.votes }));

  return (
    <div className="space-y-6">
      {Header}

      {/* Participation KPIs — unique voters vs total selections kept distinct */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <MetricCard label={t('pollAnalytics.metric.uniqueVoters')} value={fmtNum(p.unique_voters)} icon={Users} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('pollAnalytics.metric.totalSelections')} value={fmtNum(p.total_selections)} icon={ListChecks} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('pollAnalytics.metric.avgSelections')} value={p.avg_selections_per_voter.toLocaleString('en-US')} icon={Calculator} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('pollAnalytics.metric.optionsCount')} value={fmtNum(p.options_count)} icon={ListOrdered} tone="text-primary" />
      </div>

      {/* Vote distribution */}
      <Panel title={t('pollAnalytics.distribution.title')} subtitle={t('pollAnalytics.distribution.subtitle')} icon={ListChecks}>
        {a.distribution.length === 0 || p.total_selections === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('pollAnalytics.distribution.empty')}</p>
        ) : (
          <div className="space-y-3">
            {a.distribution.map((opt) => (
              <BarRow key={opt.id} label={opt.label} value={opt.votes} total={p.total_selections} />
            ))}
          </div>
        )}
      </Panel>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* Participation trend over time (full history within window) */}
        <div className="lg:col-span-2">
          <Panel
            title={t('pollAnalytics.trend.title')}
            subtitle={t('pollAnalytics.trend.subtitle')}
            icon={BarChart3}
            action={
              <span className="text-xs tabular-nums text-muted-foreground">
                {t('pollAnalytics.trend.rangeTotal', { value: fmtNum(a.trend.totals.votes) })}
              </span>
            }
          >
            <TrendChart points={trendPoints} color="bg-primary" emptyLabel={t('pollAnalytics.trend.empty')} />
          </Panel>
        </div>

        {/* Setup / configuration */}
        <Panel title={t('pollAnalytics.setup.title')} icon={Clock}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.state')}</dt>
              <dd className="font-medium">{t(`pollState.${a.entity.state}`)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.allowMultiple')}</dt>
              <dd className="font-medium">{a.entity.allow_multiple ? t('pollAnalytics.yes') : t('pollAnalytics.no')}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.audience')}</dt>
              <dd className="font-medium">{t(`audienceMode.${a.entity.audience_mode}`)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.resultVisibility')}</dt>
              <dd className="font-medium">{t(`resultVisibility.${a.entity.result_visibility}`)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.startsAt')}</dt>
              <dd className="font-medium tabular-nums">{fmtDate(a.entity.starts_at, i18n.language)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.endsAt')}</dt>
              <dd className="font-medium tabular-nums">{fmtDate(a.entity.ends_at, i18n.language)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('pollAnalytics.setup.createdAt')}</dt>
              <dd className="font-medium tabular-nums">{fmtDate(a.entity.created_at, i18n.language)}</dd>
            </div>
          </dl>
        </Panel>
      </div>
    </div>
  );
}
