import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Activity,
  ArrowRight,
  BarChart3,
  Clock,
  Eye,
  Film,
  FolderTree,
  Globe,
  Link2,
  ListVideo,
  Star,
  ThumbsDown,
  ThumbsUp,
  Users,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';
import {
  BarRow,
  DeferredNotice,
  fmtNum,
  RangeFilter,
  TrendChart,
  type RangeValue,
} from '@/components/analytics/AnalyticsKit';
import { MetricCard, Panel } from '../components/StatPrimitives';
import { useVideoEntityAnalytics } from '../hooks';

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
 * تحليلات فيديو واحد (سياقيّة) — بيانات حقيقية فقط. التفاعل تراكميّ؛ السلاسل الزمنية
 * ومصادر الزيارات «إلى-الأمام» (منذ بدء التتبّع)؛ مقاييس المشاهدة مؤجّلة بصدق.
 */
export default function VideoAnalyticsPage() {
  const { t } = useTranslation('videoLibrary');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const videoId = id ? Number(id) : null;

  const [range, setRange] = useState<RangeValue>({ range: '30d' });
  const [metric, setMetric] = useState<TrendMetric>('views');
  const q = useVideoEntityAnalytics(videoId, range.range, range.from, range.to);

  const rangeLabels: Record<string, string> = {
    '24h': t('entityAnalytics.range.24h'),
    '7d': t('entityAnalytics.range.7d'),
    '30d': t('entityAnalytics.range.30d'),
    custom: t('entityAnalytics.range.custom'),
  };

  const Header = (
    <header className="flex flex-wrap items-start justify-between gap-3">
      <div className="flex items-start gap-3">
        <Button variant="ghost" size="icon" onClick={() => navigate(paths.vlVideos)}>
          <ArrowRight className="h-4 w-4" />
        </Button>
        <div>
          <p className="text-xs font-medium text-muted-foreground">{t('entityAnalytics.title')}</p>
          <h1 className="text-2xl font-bold">{q.data?.entity.title ?? t('entityAnalytics.loadingTitle')}</h1>
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
          <span className="text-destructive">{t('entityAnalytics.error')}</span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('entityAnalytics.retry')}
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
  const trendPoints = a.trend.points.map((p) => ({ label: p.date.slice(5), value: p[metric] }));

  return (
    <div className="space-y-6">
      {Header}

      {/* KPIs — التفاعل تراكميّ (كلّ الأزمنة) */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('analytics.engagement.views')} value={fmtNum(a.engagement.views)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('entityAnalytics.uniqueReactors')} value={fmtNum(a.engagement.unique_reactors)} icon={Users} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('analytics.engagement.likes')} value={fmtNum(a.engagement.likes)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('analytics.engagement.dislikes')} value={fmtNum(a.engagement.dislikes)} icon={ThumbsDown} tone="text-destructive" />
        <MetricCard label={t('analytics.engagement.favorites')} value={fmtNum(a.engagement.favorites)} icon={Star} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('entityAnalytics.engagementRate')} value={`${a.engagement.engagement_rate}%`} icon={Activity} tone="text-primary" />
      </div>

      {/* Trend over time (forward-only) */}
      <Panel title={t('entityAnalytics.trend.title')} subtitle={t('entityAnalytics.forwardOnly')} icon={BarChart3}>
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
              {t(`analytics.engagement.${m}`)}
            </button>
          ))}
          <span className="ms-auto text-xs tabular-nums text-muted-foreground">
            {t('entityAnalytics.trend.rangeTotal', { value: fmtNum(a.trend.totals[metric]) })}
          </span>
        </div>
        <TrendChart points={trendPoints} color={METRIC_COLOR[metric]} emptyLabel={t('entityAnalytics.trend.empty')} />
      </Panel>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Traffic sources (forward-only, coarse) */}
        <Panel title={t('entityAnalytics.traffic.title')} subtitle={t('entityAnalytics.traffic.subtitle')} icon={Globe}>
          {a.traffic.total === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('entityAnalytics.traffic.empty')}</p>
          ) : (
            <div className="space-y-3">
              {CHANNELS.map((c) => (
                <BarRow key={c} label={t(`entityAnalytics.channel.${c}`)} value={a.traffic.channels[c]} total={a.traffic.total} />
              ))}
            </div>
          )}
        </Panel>

        {/* Distribution */}
        <Panel title={t('entityAnalytics.distribution.title')} icon={FolderTree}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.distribution.category')}</dt>
              <dd className="font-medium">{a.distribution.category?.name ?? t('entityAnalytics.none')}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.distribution.featured')}</dt>
              <dd className="font-medium">{a.distribution.is_featured ? t('entityAnalytics.yes') : t('entityAnalytics.no')}</dd>
            </div>
            <div className="border-t border-border pt-2.5">
              <dt className="mb-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                <ListVideo className="h-3.5 w-3.5" />
                {t('entityAnalytics.distribution.playlists')} ({a.distribution.playlists.length})
              </dt>
              {a.distribution.playlists.length === 0 ? (
                <p className="text-xs text-muted-foreground">{t('entityAnalytics.none')}</p>
              ) : (
                <ul className="space-y-1">
                  {a.distribution.playlists.map((p) => (
                    <li key={p.id} className="truncate text-sm">{p.title}</li>
                  ))}
                </ul>
              )}
            </div>
            <div className="border-t border-border pt-2.5">
              <dt className="mb-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                <Film className="h-3.5 w-3.5" />
                {t('entityAnalytics.distribution.linkedVods')} ({a.distribution.linked_vods.length})
              </dt>
              {a.distribution.linked_vods.length === 0 ? (
                <p className="text-xs text-muted-foreground">{t('entityAnalytics.none')}</p>
              ) : (
                <ul className="space-y-1">
                  {a.distribution.linked_vods.map((b) => (
                    <li key={b.id} className="truncate text-sm">{b.title}</li>
                  ))}
                </ul>
              )}
            </div>
          </dl>
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Publishing */}
        <Panel title={t('entityAnalytics.publishing.title')} icon={Clock}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.publishing.status')}</dt>
              <dd className="font-medium">{t(`status.${a.publishing.status}`)}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.publishing.visibility')}</dt>
              <dd className="font-medium">{a.publishing.visibility}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.publishing.publishedAt')}</dt>
              <dd className="font-medium tabular-nums" dir="ltr">
                {a.publishing.published_at ? new Date(a.publishing.published_at).toLocaleString() : t('entityAnalytics.none')}
              </dd>
            </div>
            {a.publishing.timeline.length > 0 ? (
              <div className="border-t border-border pt-2.5">
                <dt className="mb-1.5 text-xs text-muted-foreground">{t('entityAnalytics.publishing.timeline')}</dt>
                <ol className="space-y-1.5">
                  {a.publishing.timeline.map((row, i) => (
                    <li key={i} className="flex items-start justify-between gap-3 text-xs">
                      <span className="text-muted-foreground">
                        {row.changes.map((c) => t(`entityAnalytics.field.${c.field}`, { defaultValue: c.field })).join('، ')}
                      </span>
                      <span className="shrink-0 tabular-nums text-muted-foreground" dir="ltr">
                        {row.at ? new Date(row.at).toLocaleDateString() : ''}
                      </span>
                    </li>
                  ))}
                </ol>
              </div>
            ) : null}
          </dl>
        </Panel>

        {/* SEO / URL history */}
        <Panel title={t('entityAnalytics.seo.title')} icon={Link2}>
          <dl className="space-y-2.5 text-sm">
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.seo.slug')}</dt>
              <dd className="truncate font-medium" dir="ltr">{a.seo.slug}</dd>
            </div>
            <div className="flex items-center justify-between gap-3">
              <dt className="text-muted-foreground">{t('entityAnalytics.seo.canonical')}</dt>
              <dd className="truncate font-mono text-xs" dir="ltr">{a.seo.canonical_path}</dd>
            </div>
            <div className="border-t border-border pt-2.5">
              <dt className="mb-1.5 text-xs text-muted-foreground">
                {t('entityAnalytics.seo.redirects')} ({a.seo.redirect_history.length})
              </dt>
              {a.seo.redirect_history.length === 0 ? (
                <p className="text-xs text-muted-foreground">{t('entityAnalytics.seo.noRedirects')}</p>
              ) : (
                <ul className="space-y-1">
                  {a.seo.redirect_history.map((h, i) => (
                    <li key={i} className="truncate font-mono text-xs text-muted-foreground" dir="ltr">{h.old_path}</li>
                  ))}
                </ul>
              )}
            </div>
          </dl>
        </Panel>
      </div>

      {/* Watch metrics — deferred honestly */}
      <Panel title={t('entityAnalytics.watch.title')} icon={Activity}>
        <DeferredNotice title={t('entityAnalytics.watch.deferredTitle')} note={t('entityAnalytics.watch.deferredNote')} />
      </Panel>
    </div>
  );
}
