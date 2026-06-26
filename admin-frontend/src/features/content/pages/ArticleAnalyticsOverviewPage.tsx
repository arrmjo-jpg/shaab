import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { BarChart3, Bookmark, Clock, Eye, Globe, Sparkles, ThumbsDown, ThumbsUp, Trophy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { paths } from '@/router/paths';
import { BarRow, fmtNum, MetricCard, Panel, TrendChart } from '@/components/analytics/AnalyticsKit';
import { useArticleFleetAnalytics } from '../hooks';

/**
 * تحليلات أسطول المقالات (v1، مدى كامل) — مجاميع + لوحة صدارة + أثر التمييز + اللغة +
 * أداء وقت النشر. بيانات حقيقية فقط؛ يكمّل صفحة المقال المفردة (النطاق الزمني هناك).
 * مرآة ReelAnalyticsOverviewPage على نفس kit التحليلات المشترك — لا بنية موازية.
 */
export default function ArticleAnalyticsOverviewPage() {
  const { t } = useTranslation('content');
  const navigate = useNavigate();
  const q = useArticleFleetAnalytics();

  const Header = (
    <header>
      <h1 className="text-2xl font-bold">{t('articleAnalytics.overview.title')}</h1>
      <p className="text-sm text-muted-foreground">{t('articleAnalytics.overview.subtitle')}</p>
    </header>
  );

  if (q.isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="text-destructive">{t('articleAnalytics.error')}</span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('articleAnalytics.retry')}
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
  const fi = a.featured_impact;
  const langTotal = a.language.reduce((sum, l) => sum + l.views, 0);
  const publishPoints = a.publish_time.map((b) => ({ label: String(b.hour), value: b.avg_views }));

  return (
    <div className="space-y-6">
      {Header}

      {/* All-time note */}
      <div className="flex items-center gap-2 border border-border bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
        <Clock className="h-4 w-4 shrink-0" />
        {t('articleAnalytics.overview.allTime')}
      </div>

      {/* Fleet engagement KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <MetricCard label={t('articleAnalytics.metric.views')} value={fmtNum(a.engagement.views)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('articleAnalytics.metric.likes')} value={fmtNum(a.engagement.likes)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('articleAnalytics.metric.favorites')} value={fmtNum(a.engagement.favorites)} icon={Bookmark} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('articleAnalytics.metric.dislikes')} value={fmtNum(a.engagement.dislikes)} icon={ThumbsDown} tone="text-destructive" />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* Leaderboard */}
        <Panel title={t('articleAnalytics.leaderboard.title')} subtitle={t('articleAnalytics.leaderboard.subtitle')} icon={Trophy}>
          {a.top_performers.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('articleAnalytics.leaderboard.empty')}</p>
          ) : (
            <ol className="space-y-2">
              {a.top_performers.map((r, i) => (
                <li key={r.id}>
                  <button
                    type="button"
                    onClick={() => navigate(paths.articleAnalytics.replace(':id', String(r.id)))}
                    className="flex w-full items-center gap-3 text-start text-sm transition-colors hover:text-primary"
                  >
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center bg-muted text-xs font-semibold tabular-nums text-muted-foreground">
                      {i + 1}
                    </span>
                    <span className="min-w-0 flex-1 truncate font-medium">
                      {r.title}
                      {r.is_featured ? <Sparkles className="ms-1 inline h-3 w-3 text-amber-500" /> : null}
                    </span>
                    <span className="shrink-0 text-xs tabular-nums text-muted-foreground">
                      {fmtNum(r.views)} · {fmtNum(r.score)}
                    </span>
                  </button>
                </li>
              ))}
            </ol>
          )}
        </Panel>

        {/* Featured impact */}
        <Panel title={t('articleAnalytics.featured.title')} subtitle={t('articleAnalytics.featured.subtitle')} icon={Sparkles}>
          <div className="space-y-3">
            <div className="flex items-center justify-between border border-border p-3">
              <span className="text-sm text-muted-foreground">{t('articleAnalytics.featured.featured')}</span>
              <span className="text-lg font-bold tabular-nums">{fmtNum(fi.featured.avg_views)}</span>
            </div>
            <div className="flex items-center justify-between border border-border p-3">
              <span className="text-sm text-muted-foreground">{t('articleAnalytics.featured.regular')}</span>
              <span className="text-lg font-bold tabular-nums">{fmtNum(fi.regular.avg_views)}</span>
            </div>
            <p className="text-center text-xs text-muted-foreground">
              {fi.lift_pct === null
                ? t('articleAnalytics.featured.noBaseline')
                : t('articleAnalytics.featured.lift', { value: `${fi.lift_pct > 0 ? '+' : ''}${fi.lift_pct}` })}
            </p>
          </div>
        </Panel>

        {/* Language segmentation */}
        <Panel title={t('articleAnalytics.language.title')} icon={Globe}>
          {a.language.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground">{t('articleAnalytics.language.empty')}</p>
          ) : (
            <div className="space-y-3">
              {a.language.map((l) => (
                <BarRow key={l.locale} label={`${l.locale.toUpperCase()} · ${fmtNum(l.articles)}`} value={l.views} total={langTotal} />
              ))}
            </div>
          )}
        </Panel>
      </div>

      {/* Publish-time performance */}
      <Panel title={t('articleAnalytics.publishTime.title')} subtitle={t('articleAnalytics.publishTime.subtitle')} icon={BarChart3}>
        <TrendChart points={publishPoints} color="bg-primary" emptyLabel={t('articleAnalytics.publishTime.empty')} />
      </Panel>
    </div>
  );
}
