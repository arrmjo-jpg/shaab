import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router-dom';
import { ArrowRight, ChevronDown, Plus, Radio } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { PageSkeleton, ErrorState, EmptyState, LoadingState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { paths } from '@/router/paths';
import { useArticle, useLiveUpdates, flattenLiveUpdates } from '../hooks';
import { isEditorialUser } from '../lib/workflow';
import { LiveUpdateComposer } from '../components/LiveUpdateComposer';
import { LiveUpdateCard } from '../components/LiveUpdateCard';
import { LiveEventSettings } from '../components/LiveEventSettings';
import type { LiveEventStatus } from '@/types/content.types';

const STATUS_BADGE: Record<LiveEventStatus, string> = {
  scheduled: 'border-sky-500/40 bg-sky-500/10 text-sky-600 dark:text-sky-400',
  live: 'border-destructive/40 bg-destructive/10 text-destructive',
  paused: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400',
  completed: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
};

export default function LiveCoverageConsole() {
  const { t } = useTranslation('content');
  const { user } = useAuth();
  const { id } = useParams();
  const articleId = id ? Number(id) : 0;

  const articleQ = useArticle(articleId || null);
  const timeline = useLiveUpdates(articleId);

  const article = articleQ.data ?? null;
  const isEditorial = isEditorialUser(user?.roles ?? []);

  // ─── Single active edit mode (accordion) — one source of truth ─────────
  // Only one update editor OR the composer may be open at a time.
  const [editingId, setEditingId] = useState<number | null>(null);
  const [composerOpen, setComposerOpen] = useState(true);

  // Toggle a card's editor; opening one closes the composer + any other editor.
  const toggleEdit = (updateId: number): void => {
    setEditingId((prev) => (prev === updateId ? null : updateId));
    setComposerOpen(false);
  };

  // Opening the composer closes any active update editor.
  const toggleComposer = (): void => {
    setComposerOpen((open) => {
      if (!open) setEditingId(null);
      return !open;
    });
  };

  if (articleQ.isLoading) return <PageSkeleton />;
  if (articleQ.isError) {
    const err = articleQ.error as { message?: string; status?: number } | null;
    const detail = err?.message
      ? err.status
        ? `[${err.status}] ${err.message}`
        : err.message
      : undefined;
    return <ErrorState message={detail} onRetry={() => void articleQ.refetch()} />;
  }

  // Guard: this console only applies to live-type articles.
  if (article && article.type !== 'live') {
    return (
      <div className="space-y-4">
        <ErrorState message={t('liveCoverage.notLive')} />
        <div className="text-center">
          <Button variant="outline" asChild>
            <Link to={paths.articlesEdit.replace(':id', String(articleId))}>
              {t('liveCoverage.backToArticle')}
            </Link>
          </Button>
        </div>
      </div>
    );
  }

  const updates = flattenLiveUpdates(timeline.data);
  const eventStatus = (article?.event_status ?? 'scheduled') as LiveEventStatus;

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <nav className="flex items-center gap-2 text-sm text-muted-foreground">
          <Link to={paths.articles} className="hover:text-foreground">
            {t('articles.title')}
          </Link>
          <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
          <Link
            to={paths.articlesEdit.replace(':id', String(articleId))}
            className="hover:text-foreground"
          >
            {article?.title}
          </Link>
          <ArrowRight className="h-3.5 w-3.5 rtl:rotate-180" />
          <span className="text-foreground">{t('liveCoverage.title')}</span>
        </nav>
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="flex items-center gap-2 text-2xl font-bold">
            <Radio
              className={cn(
                'h-6 w-6',
                eventStatus === 'live' ? 'animate-pulse text-destructive' : 'text-muted-foreground',
              )}
            />
            {t('liveCoverage.title')}
          </h1>
          <span
            className={cn(
              'inline-flex items-center gap-1.5 border px-2.5 py-1 text-xs font-bold',
              STATUS_BADGE[eventStatus],
            )}
          >
            {eventStatus === 'live' ? (
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-current" />
            ) : null}
            {t(`liveCoverage.eventStatus.${eventStatus}`)}
          </span>
          <span className="text-sm text-muted-foreground">{article?.title}</span>
        </div>
      </header>

      {/* Newsroom live desk: composer + timeline (main) | event settings (side) */}
      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          {isEditorial ? (
            <div className="space-y-3">
              <button
                type="button"
                onClick={toggleComposer}
                aria-expanded={composerOpen}
                className={cn(
                  'flex w-full items-center justify-between gap-2 border px-4 py-3 text-sm font-bold transition-colors',
                  composerOpen
                    ? 'border-primary/40 bg-primary/5 text-primary'
                    : 'border-border bg-background hover:bg-muted/40',
                )}
              >
                <span className="flex items-center gap-2">
                  <Plus className="h-4 w-4" />
                  {t('liveCoverage.composer.heading')}
                </span>
                <ChevronDown
                  className={cn('h-4 w-4 transition-transform', composerOpen && 'rotate-180')}
                />
              </button>
              {composerOpen ? (
                <LiveUpdateComposer articleId={articleId} locale={article?.locale ?? 'ar'} />
              ) : null}
            </div>
          ) : (
            <div className="border border-border bg-muted/30 p-4 text-sm text-muted-foreground">
              {t('liveCoverage.readOnly')}
            </div>
          )}

          <div className="space-y-3">
            {timeline.isLoading ? (
              <LoadingState />
            ) : updates.length === 0 ? (
              <div className="border border-border bg-background">
                <EmptyState
                  title={t('liveCoverage.empty')}
                  description={t('liveCoverage.emptyHint')}
                />
              </div>
            ) : (
              <>
                {updates.map((u) => (
                  <LiveUpdateCard
                    key={u.id}
                    update={u}
                    articleId={articleId}
                    locale={article?.locale ?? 'ar'}
                    canManage={isEditorial}
                    editing={editingId === u.id}
                    onToggleEdit={() => toggleEdit(u.id)}
                  />
                ))}

                {timeline.hasNextPage ? (
                  <div className="flex justify-center pt-2">
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => void timeline.fetchNextPage()}
                      disabled={timeline.isFetchingNextPage}
                    >
                      {timeline.isFetchingNextPage
                        ? t('liveCoverage.loadingMore')
                        : t('liveCoverage.loadMore')}
                    </Button>
                  </div>
                ) : null}
              </>
            )}
          </div>
        </div>

        {isEditorial && article ? (
          <aside className="xl:col-span-1">
            <div className="xl:sticky xl:top-4">
              <LiveEventSettings article={article} />
            </div>
          </aside>
        ) : null}
      </div>
    </div>
  );
}
