import { useTranslation } from 'react-i18next';
import {
  BarChart3,
  Bookmark,
  Clapperboard,
  Eye,
  FileText,
  Film,
  Megaphone,
  MousePointerClick,
  Newspaper,
  RadioTower,
  ThumbsUp,
  Vote,
  type LucideIcon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { fmtNum, MetricCard, Panel, TrendChart } from '@/components/analytics/AnalyticsKit';
import { useSiteAnalytics } from '../dashboard.hooks';
import QuickActions from '../components/QuickActions';
import RecentContent from '../components/RecentContent';
import PendingModeration from '../components/PendingModeration';
import ServerStatus from '../components/ServerStatus';
import CacheControls from '../components/CacheControls';
import TopContent from '../components/TopContent';
import TrafficChannels from '../components/TrafficChannels';

/**
 * KPIs الموقع + الجرد + اتجاه المشاهدات (قراءة-فقط). قسم مُكتفٍ ذاتياً يعالج
 * تحميله/خطأه داخلياً (لا يُسقط بقية اللوحة عند 403 على analytics.view).
 */
function SiteKpis() {
  const { t } = useTranslation('common');
  const q = useSiteAnalytics();

  if (q.isError) {
    return (
      <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
        <span className="text-destructive">{t('dashboard.error')}</span>
        <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
          {t('dashboard.retry')}
        </Button>
      </div>
    );
  }

  if (q.isLoading || !q.data) {
    return (
      <div className="space-y-6">
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
  const trendPoints = a.trend.map((p) => ({ label: p.date.slice(5), value: p.views }));
  const inventory: Array<{ key: string; count: number; icon: LucideIcon }> = [
    { key: 'articles', count: a.inventory.articles, icon: FileText },
    { key: 'reels', count: a.inventory.reels, icon: Clapperboard },
    { key: 'videos', count: a.inventory.videos, icon: Film },
    { key: 'broadcasts', count: a.inventory.broadcasts, icon: RadioTower },
    { key: 'polls', count: a.inventory.polls, icon: Vote },
    { key: 'epapers', count: a.inventory.epapers, icon: Newspaper },
  ];

  return (
    <div className="space-y-6">
      {/* Engagement + ads + polls KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <MetricCard label={t('dashboard.kpi.views')} value={fmtNum(a.engagement.views)} icon={Eye} tone="text-sky-600 dark:text-sky-400" />
        <MetricCard label={t('dashboard.kpi.likes')} value={fmtNum(a.engagement.likes)} icon={ThumbsUp} tone="text-emerald-600 dark:text-emerald-400" />
        <MetricCard label={t('dashboard.kpi.favorites')} value={fmtNum(a.engagement.favorites)} icon={Bookmark} tone="text-amber-600 dark:text-amber-400" />
        <MetricCard label={t('dashboard.kpi.impressions')} value={fmtNum(a.ads.impressions)} icon={Megaphone} tone="text-violet-600 dark:text-violet-400" />
        <MetricCard label={t('dashboard.kpi.clicks')} value={fmtNum(a.ads.clicks)} icon={MousePointerClick} tone="text-primary" />
        <MetricCard label={t('dashboard.kpi.votes')} value={fmtNum(a.polls.votes)} icon={Vote} tone="text-rose-600 dark:text-rose-400" />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        {/* Content inventory */}
        <Panel title={t('dashboard.inventory.title')} icon={BarChart3}>
          <dl className="space-y-2.5 text-sm">
            {inventory.map(({ key, count, icon: Icon }) => (
              <div key={key} className="flex items-center justify-between gap-3">
                <dt className="flex items-center gap-2 text-muted-foreground">
                  <Icon className="h-3.5 w-3.5" />
                  {t(`dashboard.inventory.${key}`)}
                </dt>
                <dd className="font-bold tabular-nums">{fmtNum(count)}</dd>
              </div>
            ))}
          </dl>
        </Panel>

        {/* Content-views trend (30d) */}
        <div className="lg:col-span-2">
          <Panel title={t('dashboard.trend.title')} subtitle={t('dashboard.trend.subtitle')} icon={BarChart3}>
            <TrendChart points={trendPoints} color="bg-primary" emptyLabel={t('dashboard.trend.empty')} />
          </Panel>
        </div>
      </div>

      {/* Phase B — Top Content + Traffic Channels (نفس الحمولة المكاشة؛ محروسان ضدّ غياب v1 قديمة) */}
      {a.top ? <TopContent top={a.top} /> : null}
      {a.channels ? <TrafficChannels channels={a.channels} /> : null}
    </div>
  );
}

/**
 * لوحة الإدارة V2 (قراءة-فقط) — Quick Actions + KPIs + Recent Content + Pending
 * Moderation + Cache Controls + Server Status. كل widget مُكتفٍ ذاتياً (حالة/صلاحية
 * مستقلّة)؛ `/` يبقى مفتوحاً لأي أدمن والصلاحيات تتحكّم بظهور كل قسم.
 */
export default function DashboardPage() {
  const { t } = useTranslation('common');

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
      </header>

      <QuickActions />
      <SiteKpis />
      <RecentContent />

      <div className="grid gap-4 lg:grid-cols-2">
        <PendingModeration />
        <CacheControls />
      </div>

      <ServerStatus />
    </div>
  );
}
