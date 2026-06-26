import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { ArticlesReviewTab } from './ArticlesReviewTab';
import { ReelsReviewTab } from './ReelsReviewTab';
import { VideosReviewTab } from './VideosReviewTab';

type TabKey = 'articles' | 'reels' | 'videos';

/**
 * Editorial Review Queue (P1.3 S2-B) — one page, three typed tabs
 * (Articles / Reels / Videos). Each tab lists writer-submitted content
 * (status=submitted) and lets an editor Publish/Reject, reusing the existing
 * per-type list + transition hooks. Route is gated by articles.view; the Reels
 * and Videos tabs appear only when the user also holds reels.view / videos.view
 * (so a tab is never shown for content the backend would 403 on listing).
 * Pure reuse: no new backend, permissions, or notifications.
 */
export default function ReviewQueuePage() {
  const { t } = useTranslation('content');
  const { hasPermission } = useAuth();

  const tabs = (
    [
      { key: 'articles', show: true },
      { key: 'reels', show: hasPermission('reels.view') },
      { key: 'videos', show: hasPermission('videos.view') },
    ] satisfies ReadonlyArray<{ key: TabKey; show: boolean }>
  ).filter((tab) => tab.show);

  const [active, setActive] = useState<TabKey>('articles');

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-xl font-bold">{t('reviewQueue.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('reviewQueue.subtitle')}</p>
      </header>

      <div className="flex flex-wrap gap-1 border-b border-border">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            type="button"
            onClick={() => setActive(tab.key)}
            className={cn(
              'border-b-2 px-4 py-2 text-sm font-medium transition-colors',
              active === tab.key
                ? 'border-primary text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
          >
            {t(`reviewQueue.tabs.${tab.key}`)}
          </button>
        ))}
      </div>

      {active === 'articles' ? <ArticlesReviewTab /> : null}
      {active === 'reels' && hasPermission('reels.view') ? <ReelsReviewTab /> : null}
      {active === 'videos' && hasPermission('videos.view') ? <VideosReviewTab /> : null}
    </div>
  );
}
