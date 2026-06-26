import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  BarChart3,
  Clock,
  Eye,
  FolderTree,
  ListVideo,
  Star,
  ThumbsDown,
  ThumbsUp,
  TrendingUp,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useVideoAnalytics, useVideoDashboard } from '../hooks';
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

type ReactionKey = 'likes' | 'favorites' | 'dislikes';
const REACTION_KEYS: ReactionKey[] = ['likes', 'favorites', 'dislikes'];
const REACTION_BAR: Record<ReactionKey, string> = {
  likes: 'bg-emerald-500',
  favorites: 'bg-amber-500',
  dislikes: 'bg-destructive',
};

/**
 * تحليلات مكتبة الفيديو — بيانات حقيقية فقط من نقطتي /analytics و /dashboard
 * (تفاعل، توزيع مصادر/حالات، الرائج، الأكثر مشاهدة/قوائم/تصنيفات). لا رسوم وهمية.
 */
export default function AnalyticsPage() {
  const { t, i18n } = useTranslation('videoLibrary');
  const analytics = useVideoAnalytics();
  const dashboard = useVideoDashboard();
  const locale = i18n.language;

  const isLoading = analytics.isLoading || dashboard.isLoading;
  const isError = analytics.isError || dashboard.isError;
  const refetch = () => {
    void analytics.refetch();
    void dashboard.refetch();
  };

  const Header = (
    <header>
      <h1 className="text-2xl font-bold">{t('analytics.title')}</h1>
      <p className="text-sm text-muted-foreground">{t('analytics.subtitle')}</p>
    </header>
  );

  if (isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('analytics.error')}
          </span>
          <Button variant="outline" size="sm" onClick={refetch}>
            {t('analytics.retry')}
          </Button>
        </div>
      </div>
    );
  }

  if (isLoading || !analytics.data || !dashboard.data) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
          <Skeleton className="h-56 w-full" />
          <Skeleton className="h-56 w-full" />
        </div>
      </div>
    );
  }

  const eng = analytics.data.engagement;
  const d = dashboard.data;
  const reactionTotal = REACTION_KEYS.reduce((a, k) => a + eng[k], 0);
  const sourceTotal = SOURCE_KEYS.reduce((a, k) => a + d.source_distribution[k], 0);
  const statusTotal = STATUS_KEYS.reduce((a, k) => a + d.status_counts[k], 0) || 1;
  const maxTrend = analytics.data.trending.reduce((m, v) => Math.max(m, v.score), 0) || 1;

  return (
    <div className="space-y-6">
      {Header}

      {/* Time context — كل الأرقام تراكمية */}
      <div className="flex items-center gap-2 border border-border bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
        <Clock className="h-4 w-4 shrink-0" />
        {t('analytics.allTime')}
      </div>

      {/* Engagement KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <MetricCard label={t('analytics.engagement.views')} value={fmt(eng.views, locale)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('analytics.engagement.likes')} value={fmt(eng.likes, locale)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('analytics.engagement.favorites')} value={fmt(eng.favorites, locale)} icon={Star} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('analytics.engagement.dislikes')} value={fmt(eng.dislikes, locale)} icon={ThumbsDown} tone="text-destructive" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Engagement distribution */}
        <Panel title={t('analytics.distribution.title')} subtitle={t('analytics.distribution.subtitle')} icon={BarChart3}>
          {reactionTotal === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.distribution.empty')}</p>
          ) : (
            <div className="space-y-3">
              {REACTION_KEYS.map((k) => {
                const v = eng[k];
                const pct = Math.round((v / reactionTotal) * 100);
                return (
                  <div key={k}>
                    <div className="mb-1 flex items-center justify-between text-xs">
                      <span className="font-medium">{t(`analytics.engagement.${k}`)}</span>
                      <span className="tabular-nums text-muted-foreground">
                        {fmt(v, locale)} · {pct}%
                      </span>
                    </div>
                    <div className="h-2 w-full overflow-hidden bg-muted">
                      <div className={cn('h-full', REACTION_BAR[k])} style={{ width: `${pct}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Panel>

        {/* Source distribution */}
        <Panel title={t('analytics.sources.title')} subtitle={t('analytics.sources.subtitle')} icon={BarChart3}>
          {sourceTotal === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.sources.empty')}</p>
          ) : (
            <div className="space-y-3">
              {SOURCE_KEYS.map((k) => {
                const v = d.source_distribution[k];
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

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Publication status distribution */}
        <Panel title={t('analytics.status.title')} icon={BarChart3}>
          <div className="space-y-3">
            {STATUS_KEYS.map((k) => {
              const v = d.status_counts[k];
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

        {/* Trending */}
        <Panel title={t('analytics.trending.title')} subtitle={t('analytics.trending.subtitle')} icon={TrendingUp}>
          {analytics.data.trending.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.trending.empty')}</p>
          ) : (
            <ol className="space-y-3">
              {analytics.data.trending.map((v, i) => {
                const pct = Math.round((v.score / maxTrend) * 100);
                return (
                  <li key={v.id} className="space-y-1.5">
                    <div className="flex items-center gap-3 text-sm">
                      <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-muted text-xs font-semibold tabular-nums text-muted-foreground">
                        {i + 1}
                      </span>
                      <span className="min-w-0 flex-1 truncate font-medium">{v.title}</span>
                      <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                        {fmt(v.score, locale)} {t('analytics.trending.score')}
                      </span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden bg-muted">
                      <div className="h-full bg-primary" style={{ width: `${pct}%` }} />
                    </div>
                  </li>
                );
              })}
            </ol>
          )}
        </Panel>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* Top videos */}
        <Panel title={t('analytics.topVideos.title')} icon={Eye}>
          {d.top_videos.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.topVideos.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {d.top_videos.map((v, i) => (
                <li key={v.id} className="flex items-center gap-3 text-sm">
                  <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-muted text-xs font-semibold tabular-nums text-muted-foreground">
                    {i + 1}
                  </span>
                  <span className="min-w-0 flex-1 truncate font-medium">{v.title}</span>
                  <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {fmt(v.views_count, locale)} {t('analytics.topVideos.views')}
                  </span>
                </li>
              ))}
            </ol>
          )}
        </Panel>

        {/* Top playlists */}
        <Panel title={t('analytics.topPlaylists.title')} icon={ListVideo}>
          {analytics.data.top_playlists.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.topPlaylists.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {analytics.data.top_playlists.map((p) => (
                <li key={p.id} className="flex items-center gap-3 text-sm">
                  <span className="min-w-0 flex-1 truncate font-medium">{p.title}</span>
                  <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {fmt(p.videos_count, locale)} {t('analytics.topPlaylists.count')}
                  </span>
                </li>
              ))}
            </ol>
          )}
        </Panel>

        {/* Top categories */}
        <Panel title={t('analytics.topCategories.title')} icon={FolderTree}>
          {d.top_categories.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('analytics.topCategories.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {d.top_categories.map((c) => (
                <li key={c.id} className="flex items-center gap-3 text-sm">
                  <span className="min-w-0 flex-1 truncate font-medium">{c.name}</span>
                  <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                    {fmt(c.videos_count, locale)} {t('analytics.topCategories.count')}
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
