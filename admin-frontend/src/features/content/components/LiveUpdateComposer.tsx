import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Image as ImageIcon, Pin, Send, Star, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/useToast';
import { ArticleEditor } from '../editor/ArticleEditor';
import { MediaStudio } from './media/MediaStudio';
import { LiveUpdateMedia, stagingToPreview } from './LiveUpdateMedia';
import { useMediaStaging } from '../lib/useMediaStaging';
import { useCreateLiveUpdate } from '../hooks';
import type { ContentLocale } from '@/types/content.types';

const EMPTY_DOC = { type: 'doc', content: [{ type: 'paragraph' }] };

interface Props {
  articleId: number;
  locale: ContentLocale;
}

/**
 * Speed-first composer: title (optional) + compact TipTap + pin toggle + Post.
 * Ctrl/Cmd+Enter posts. On success the editor resets to an empty doc so the
 * editor can fire the next update immediately. Posting is optimistic (the hook
 * prepends instantly), so the timeline updates without waiting on the network.
 */
export function LiveUpdateComposer({ articleId, locale }: Props) {
  const { t } = useTranslation('content');
  const { success, error: toastError } = useToast();
  const create = useCreateLiveUpdate(articleId);
  const staging = useMediaStaging();

  const [title, setTitle] = useState('');
  const [pinned, setPinned] = useState(false);
  const [breaking, setBreaking] = useState(false);
  const [featured, setFeatured] = useState(false);
  const [doc, setDoc] = useState<unknown>(EMPTY_DOC);
  // Bump to force-remount the editor after a successful post (clears content).
  const [editorKey, setEditorKey] = useState(0);

  const isEmptyDoc = (value: unknown): boolean => {
    const root = value as { content?: Array<{ content?: unknown[] }> } | null;
    if (!root?.content || root.content.length === 0) return true;
    // Single empty paragraph = empty
    if (root.content.length === 1) {
      const only = root.content[0];
      return !only.content || only.content.length === 0;
    }
    return false;
  };

  const post = () => {
    if (isEmptyDoc(doc)) {
      toastError(t('liveCoverage.composer.empty'));
      return;
    }
    create.mutate(
      {
        title: title.trim() ? title.trim() : null,
        content_json: doc,
        is_pinned: pinned,
        is_breaking: breaking,
        is_featured: featured,
        media: staging.toPayload(),
      },
      {
        onSuccess: () => {
          success(t('liveCoverage.composer.posted'));
          setTitle('');
          setPinned(false);
          setBreaking(false);
          setFeatured(false);
          setDoc(EMPTY_DOC);
          staging.reset([]);
          setEditorKey((k) => k + 1);
        },
      },
    );
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      post();
    }
  };

  const mediaCount =
    (staging.cover ? 1 : 0) + staging.gallery.length + staging.videos.length;
  const preview = stagingToPreview(staging);

  return (
    <section className="border border-border bg-background p-4" onKeyDown={onKeyDown}>
      {/* Newsroom live composer: wide content (RTL right) + narrow media
          companion (left), mirroring the article form (8/4). Stacks on small. */}
      <div className="grid gap-5 lg:grid-cols-12">
        {/* Primary content (RTL right): title + editor + post controls */}
        <div className="space-y-3 lg:col-span-8">
          <div className="flex flex-wrap items-center gap-2">
            <Input
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder={t('liveCoverage.composer.titlePlaceholder')}
              maxLength={200}
              className="h-10 flex-1"
            />
            <button
              type="button"
              onClick={() => setPinned((v) => !v)}
              aria-pressed={pinned}
              title={t('liveCoverage.composer.pin')}
              className={cn(
                'inline-flex h-10 items-center gap-2 border px-3 text-sm transition-colors',
                pinned
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-input bg-background text-muted-foreground hover:text-foreground',
              )}
            >
              <Pin className="h-4 w-4" />
              {pinned ? t('liveCoverage.composer.pinned') : t('liveCoverage.composer.pin')}
            </button>
            <button
              type="button"
              onClick={() => setBreaking((v) => !v)}
              aria-pressed={breaking}
              title={t('liveCoverage.composer.breaking')}
              className={cn(
                'inline-flex h-10 w-10 items-center justify-center border transition-colors',
                breaking
                  ? 'border-destructive bg-destructive/10 text-destructive'
                  : 'border-input bg-background text-muted-foreground hover:text-foreground',
              )}
            >
              <Zap className="h-4 w-4" />
            </button>
            <button
              type="button"
              onClick={() => setFeatured((v) => !v)}
              aria-pressed={featured}
              title={t('liveCoverage.composer.featured')}
              className={cn(
                'inline-flex h-10 w-10 items-center justify-center border transition-colors',
                featured
                  ? 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                  : 'border-input bg-background text-muted-foreground hover:text-foreground',
              )}
            >
              <Star className="h-4 w-4" />
            </button>
          </div>

          <ArticleEditor
            key={editorKey}
            value={doc}
            onChange={setDoc}
            articleId={articleId}
            locale={locale}
          />

          <div className="flex items-center justify-between gap-3 border-t border-border pt-3">
            <p className="text-xs text-muted-foreground">{t('liveCoverage.composer.shortcut')}</p>
            <Button type="button" onClick={post} disabled={create.isPending}>
              <Send className="h-4 w-4" />
              {create.isPending
                ? t('liveCoverage.composer.posting')
                : t('liveCoverage.composer.post')}
            </Button>
          </div>
        </div>

        {/* Media companion (RTL left) — narrow sidebar, sticky on large screens */}
        <div className="space-y-3 border-border lg:sticky lg:top-4 lg:col-span-4 lg:self-start lg:border-s lg:ps-5">
          <div className="flex items-center gap-2 text-xs font-bold uppercase text-muted-foreground">
            <ImageIcon className="h-4 w-4" />
            {t('liveCoverage.composer.media')}
            {mediaCount > 0 ? (
              <span className="bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">
                {mediaCount}
              </span>
            ) : null}
          </div>

          <MediaStudio staging={staging} />

          {mediaCount > 0 ? (
            <div className="border-t border-border pt-3">
              <p className="mb-1 text-xs font-bold uppercase text-muted-foreground">
                {t('liveCoverage.composer.mediaPreview')}
              </p>
              <LiveUpdateMedia images={preview.images} videos={preview.videos} />
            </div>
          ) : null}
        </div>
      </div>
    </section>
  );
}
