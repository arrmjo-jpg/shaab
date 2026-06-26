import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Clapperboard, FileText, Film, type LucideIcon } from 'lucide-react';
import { Panel } from '@/components/analytics/AnalyticsKit';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/stores/auth.store';
import { paths } from '@/router/paths';
import { useRecentArticles, useRecentReels, useRecentVideos } from '../dashboard.hooks';

interface RecentItem {
  id: number;
  title: string;
  status: string;
}

function RecentSection({
  title,
  icon: Icon,
  isLoading,
  isError,
  items,
  editPath,
}: {
  title: string;
  icon: LucideIcon;
  isLoading: boolean;
  isError: boolean;
  items: RecentItem[];
  editPath: (id: number) => string;
}) {
  const { t } = useTranslation('common');

  return (
    <Panel title={title} icon={Icon}>
      {isError ? (
        <p className="text-sm text-destructive">{t('dashboard.error')}</p>
      ) : isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 5 }).map((_, i) => (
            <Skeleton key={i} className="h-7 w-full" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('dashboard.recent.empty')}</p>
      ) : (
        <ul className="divide-y divide-border">
          {items.map((it) => (
            <li key={it.id}>
              <Link
                to={editPath(it.id)}
                className="flex items-center justify-between gap-3 py-2 text-sm transition-colors hover:text-primary"
              >
                <span className="truncate">{it.title}</span>
                <span className="shrink-0 border border-border px-1.5 py-0.5 text-xs text-muted-foreground">
                  {it.status}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Panel>
  );
}

/** آخر 10 لكل نوع — يعيد استخدام endpoints القوائم القائمة (sort=-created_at، per_page=10). */
export default function RecentContent() {
  const { t } = useTranslation('common');
  const { hasPermission } = useAuth();
  const canArticles = hasPermission('articles.view');
  const canReels = hasPermission('reels.view');
  const canVideos = hasPermission('videos.view');

  const articles = useRecentArticles(canArticles);
  const reels = useRecentReels(canReels);
  const videos = useRecentVideos(canVideos);

  if (!canArticles && !canReels && !canVideos) return null;

  return (
    <div className="grid gap-4 lg:grid-cols-3">
      {canArticles ? (
        <RecentSection
          title={t('dashboard.recent.articles')}
          icon={FileText}
          isLoading={articles.isLoading}
          isError={articles.isError}
          items={(articles.data?.data ?? []).map((a) => ({ id: a.id, title: a.title, status: a.status }))}
          editPath={(id) => paths.articlesEdit.replace(':id', String(id))}
        />
      ) : null}
      {canReels ? (
        <RecentSection
          title={t('dashboard.recent.reels')}
          icon={Clapperboard}
          isLoading={reels.isLoading}
          isError={reels.isError}
          items={(reels.data?.data ?? []).map((r) => ({ id: r.id, title: r.title, status: r.status }))}
          editPath={(id) => paths.reelsEdit.replace(':id', String(id))}
        />
      ) : null}
      {canVideos ? (
        <RecentSection
          title={t('dashboard.recent.videos')}
          icon={Film}
          isLoading={videos.isLoading}
          isError={videos.isError}
          items={(videos.data?.data ?? []).map((v) => ({ id: v.id, title: v.title, status: v.status }))}
          editPath={(id) => paths.vlVideosEdit.replace(':id', String(id))}
        />
      ) : null}
    </div>
  );
}
