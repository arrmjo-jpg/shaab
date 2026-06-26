import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  BarChart3,
  CheckCircle2,
  Eye,
  FolderTree,
  ListVideo,
  Loader2,
  Star,
  Video,
} from 'lucide-react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useVideoDashboard } from '../hooks';
import { MetricCard, Panel } from '../components/StatPrimitives';
import type { VideoSourceType } from '@/types/videoLibrary.types';

function fmt(n: number, locale: string): string {
  return n.toLocaleString(locale);
}

const SOURCE_KEYS: VideoSourceType[] = ['uploaded', 'youtube', 'vimeo', 'direct_mp4'];
const SOURCE_BAR: Record<VideoSourceType, string> = {
  uploaded: 'bg-primary',
  youtube: 'bg-destructive',
  vimeo: 'bg-sky-500',
  direct_mp4: 'bg-emerald-500',
};
const STATUS_KEYS = ['published', 'scheduled', 'draft', 'archived'] as const;

export default function VideoDashboardPage() {
  const { t, i18n } = useTranslation('videoLibrary');
  const { data, isLoading } = useVideoDashboard();

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

  const locale = i18n.language;
  const sc = data.status_counts;
  const ph = data.processing_health;
  const sourceTotal = SOURCE_KEYS.reduce((a, k) => a + data.source_distribution[k], 0);
  const statusTotal = STATUS_KEYS.reduce((a, k) => a + sc[k], 0) || 1;
  const healthy = ph.failed === 0;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
      </header>

      {/* KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('dashboard.kpi.totalVideos')} value={fmt(sc.total, locale)} icon={Video} />
        <MetricCard label={t('dashboard.kpi.totalViews')} value={fmt(data.total_views, locale)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('dashboard.kpi.published')} value={fmt(sc.published, locale)} icon={CheckCircle2} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('dashboard.kpi.featured')} value={fmt(data.featured, locale)} icon={Star} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('dashboard.kpi.playlists')} value={fmt(data.playlists.total, locale)} icon={ListVideo} />
        <MetricCard label={t('dashboard.kpi.categories')} value={fmt(data.categories.total, locale)} icon={FolderTree} />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Processing health */}
        <Panel title={t('dashboard.processing.title')} subtitle={t('dashboard.processing.subtitle')} icon={Loader2}>
          <div className="grid grid-cols-3 gap-3">
            <div className="border border-border p-3 text-center">
              <p className="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{fmt(ph.ready, locale)}</p>
              <p className="text-xs text-muted-foreground">{t('dashboard.processing.ready')}</p>
            </div>
            <div className="border border-border p-3 text-center">
              <p className="text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{fmt(ph.processing, locale)}</p>
              <p className="text-xs text-muted-foreground">{t('dashboard.processing.processing')}</p>
            </div>
            <div className={cn('border p-3 text-center', ph.failed > 0 ? 'border-destructive' : 'border-border')}>
              <p className={cn('text-2xl font-bold tabular-nums', ph.failed > 0 ? 'text-destructive' : 'text-muted-foreground')}>
                {fmt(ph.failed, locale)}
              </p>
              <p className="text-xs text-muted-foreground">{t('dashboard.processing.failed')}</p>
            </div>
          </div>
          <div className={cn('mt-3 flex items-center gap-2 text-xs', healthy ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive')}>
            {healthy ? <CheckCircle2 className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
            {healthy ? t('dashboard.processing.healthy') : t('dashboard.processing.attention')}
          </div>
        </Panel>

        {/* Source distribution */}
        <Panel title={t('dashboard.sources.title')} subtitle={t('dashboard.sources.subtitle')} icon={BarChart3}>
          {sourceTotal === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('dashboard.sources.empty')}</p>
          ) : (
            <div className="space-y-3">
              {SOURCE_KEYS.map((k) => {
                const v = data.source_distribution[k];
                const pct = Math.round((v / sourceTotal) * 100);
                return (
                  <div key={k}>
                    <div className="mb-1 flex items-center justify-between text-xs">
                      <span className="font-medium">{t(`source.${k}`)}</span>
                      <span className="tabular-nums text-muted-foreground">
                        {fmt(v, locale)} · {pct}%
                      </span>
                    </div>
                    <div className="h-2 w-full overflow-hidden bg-muted">
                      <div className={cn('h-full', SOURCE_BAR[k])} style={{ width: `${pct}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* Status breakdown */}
        <Panel title={t('dashboard.statusMix.title')}>
          <div className="space-y-3">
            {STATUS_KEYS.map((k) => {
              const v = sc[k];
              const pct = Math.round((v / statusTotal) * 100);
              return (
                <div key={k}>
                  <div className="mb-1 flex items-center justify-between text-xs">
                    <span className="font-medium">{t(`status.${k}`)}</span>
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

        {/* Top videos */}
        <Panel title={t('dashboard.topVideos.title')} icon={Eye}>
          {data.top_videos.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('dashboard.topVideos.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {data.top_videos.map((v, i) => (
                <li key={v.id} className="flex items-center gap-3 text-sm">
                  <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-muted text-xs font-semibold tabular-nums text-muted-foreground">
                    {i + 1}
                  </span>
                  <span className="min-w-0 flex-1 truncate font-medium">{v.title}</span>
                  <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {fmt(v.views_count, locale)} {t('dashboard.topVideos.views')}
                  </span>
                </li>
              ))}
            </ol>
          )}
        </Panel>

        {/* Top categories */}
        <Panel title={t('dashboard.topCategories.title')} icon={FolderTree}>
          {data.top_categories.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('dashboard.topCategories.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {data.top_categories.map((c) => (
                <li key={c.id} className="flex items-center gap-3 text-sm">
                  <span className="min-w-0 flex-1 truncate font-medium">{c.name}</span>
                  <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {fmt(c.videos_count, locale)} {t('dashboard.topCategories.count')}
                  </span>
                </li>
              ))}
            </ol>
          )}
        </Panel>
      </div>
    </div>
  );
}
