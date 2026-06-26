import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  ArrowDown,
  ArrowUp,
  Check,
  Clock,
  Image as ImageIcon,
  Pencil,
  Pin,
  PinOff,
  Star,
  Trash2,
  X,
  Zap,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/useToast';
import { ArticleEditor } from '../editor/ArticleEditor';
import { MediaStudio } from './media/MediaStudio';
import {
  LiveUpdateMedia,
  stagingToPreview,
  type PreviewImage,
  type PreviewVideo,
} from './LiveUpdateMedia';
import { useMediaStaging, stagedFromMedia } from '../lib/useMediaStaging';
import { useDeleteLiveUpdate, useMoveLiveUpdate, useUpdateLiveUpdate } from '../hooks';
import type { ContentLocale, LiveUpdateData } from '@/types/content.types';

/** Map the shared media block to the polished preview component's props. */
function mediaToPreview(media: LiveUpdateData['media']): {
  images: PreviewImage[];
  videos: PreviewVideo[];
} {
  if (!media) return { images: [], videos: [] };
  const images: PreviewImage[] = [...(media.cover ? [media.cover] : []), ...media.gallery].map(
    (i) => ({ id: i.id, url: i.url, thumb: i.thumb ?? null, alt: i.alt ?? null }),
  );
  const videos: PreviewVideo[] = media.video.map((v) => ({
    id: v.id,
    url: v.url,
    poster: v.poster ?? null,
    isExternal: v.is_external,
    provider: v.provider ?? null,
  }));
  return { images, videos };
}

/** Plain-text snippet from sanitized content HTML (entities decoded, tags stripped). */
function htmlToText(html: string): string {
  if (!html) return '';
  const doc = new DOMParser().parseFromString(html, 'text/html');
  return (doc.body.textContent ?? '').replace(/\s+/g, ' ').trim();
}

interface Props {
  update: LiveUpdateData;
  articleId: number;
  locale: ContentLocale;
  canManage: boolean;
  /** Single source of truth: this card is the one being edited (console-owned). */
  editing: boolean;
  /** Toggle edit for this update (console closes any other open editor/composer). */
  onToggleEdit: () => void;
}

export function LiveUpdateCard({
  update,
  articleId,
  locale,
  canManage,
  editing,
  onToggleEdit,
}: Props) {
  const { t, i18n } = useTranslation('content');
  const { confirm } = useToast();
  const updateMut = useUpdateLiveUpdate(articleId);
  const deleteMut = useDeleteLiveUpdate(articleId);
  const moveMut = useMoveLiveUpdate(articleId);

  const [title, setTitle] = useState(update.title ?? '');
  const [doc, setDoc] = useState<unknown>(update.content_json);
  const staging = useMediaStaging();

  // Seed the edit form from the update each time this card enters edit mode.
  useEffect(() => {
    if (editing) {
      setTitle(update.title ?? '');
      setDoc(update.content_json);
      staging.reset(update.media ? stagedFromMedia(update.media) : []);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing]);
  const preview = mediaToPreview(update.media);
  const thumb =
    preview.images[0]?.thumb ??
    preview.images[0]?.url ??
    preview.videos.find((v) => v.poster)?.poster ??
    null;
  const mediaCount = preview.images.length + preview.videos.length;
  const snippet = htmlToText(update.content_html ?? '');

  const fmtTime = (v: string | null): string =>
    v
      ? new Intl.DateTimeFormat(i18n.language, {
          dateStyle: 'short',
          timeStyle: 'short',
        }).format(new Date(v))
      : '—';

  const isOptimistic = update.id < 0;

  const togglePin = () => {
    updateMut.mutate({ id: update.id, payload: { is_pinned: !update.is_pinned } });
  };

  const toggleBreaking = () => {
    updateMut.mutate({ id: update.id, payload: { is_breaking: !update.is_breaking } });
  };

  const toggleFeatured = () => {
    updateMut.mutate({ id: update.id, payload: { is_featured: !update.is_featured } });
  };

  const saveEdit = () => {
    updateMut.mutate(
      {
        id: update.id,
        payload: {
          title: title.trim() ? title.trim() : null,
          content_json: doc,
          media: staging.toPayload(),
        },
      },
      { onSuccess: () => onToggleEdit() },
    );
  };

  const onDelete = async () => {
    if (
      await confirm({
        title: t('liveCoverage.card.deleteTitle'),
        text: t('liveCoverage.card.deleteText'),
        confirmText: t('liveCoverage.card.deleteYes'),
        cancelText: t('articles.form.cancel'),
      })
    )
      deleteMut.mutate(update.id);
  };

  return (
    <article
      className={cn(
        'border border-border bg-background',
        update.is_breaking
          ? 'border-s-4 border-s-destructive'
          : update.is_featured
            ? 'border-s-4 border-s-amber-500'
            : update.is_pinned && 'border-s-4 border-s-primary',
        isOptimistic && 'opacity-60',
      )}
    >
      <header className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-2.5">
        {/* Timestamp prominence — newsroom timeline anchor */}
        <span className="inline-flex items-center gap-1.5 text-sm font-bold text-foreground">
          <Clock className="h-4 w-4 text-primary" />
          {fmtTime(update.happened_at)}
        </span>
        {update.is_breaking ? (
          <Badge variant="destructive" className="gap-1">
            <Zap className="h-3 w-3" />
            {t('liveCoverage.card.breakingBadge')}
          </Badge>
        ) : null}
        {update.is_featured ? (
          <Badge className="gap-1 border-transparent bg-amber-500/15 text-amber-600 dark:text-amber-400">
            <Star className="h-3 w-3" />
            {t('liveCoverage.card.featuredBadge')}
          </Badge>
        ) : null}
        {update.is_pinned ? (
          <Badge variant="muted" className="gap-1">
            <Pin className="h-3 w-3" />
            {t('liveCoverage.card.pinnedBadge')}
          </Badge>
        ) : null}
        {update.author?.name ? (
          <span className="text-xs text-muted-foreground">· {update.author.name}</span>
        ) : null}

        {canManage && !isOptimistic ? (
          <div className="ms-auto flex items-center gap-1">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={togglePin}
              disabled={updateMut.isPending}
              title={update.is_pinned ? t('liveCoverage.card.unpin') : t('liveCoverage.card.pin')}
            >
              {update.is_pinned ? <PinOff className="h-4 w-4" /> : <Pin className="h-4 w-4" />}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={cn('h-8 w-8', update.is_breaking && 'text-destructive')}
              onClick={toggleBreaking}
              disabled={updateMut.isPending}
              title={t('liveCoverage.card.breaking')}
            >
              <Zap className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={cn('h-8 w-8', update.is_featured && 'text-amber-600 dark:text-amber-400')}
              onClick={toggleFeatured}
              disabled={updateMut.isPending}
              title={t('liveCoverage.card.featured')}
            >
              <Star className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={() => moveMut.mutate({ id: update.id, direction: 'up' })}
              disabled={moveMut.isPending}
              title={t('liveCoverage.card.moveUp')}
            >
              <ArrowUp className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-8 w-8"
              onClick={() => moveMut.mutate({ id: update.id, direction: 'down' })}
              disabled={moveMut.isPending}
              title={t('liveCoverage.card.moveDown')}
            >
              <ArrowDown className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={cn('h-8 w-8', editing && 'bg-primary/15 text-primary')}
              onClick={onToggleEdit}
              aria-pressed={editing}
              title={t('liveCoverage.card.edit')}
            >
              <Pencil className="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className="h-8 w-8 text-destructive hover:bg-destructive/10"
              onClick={onDelete}
              disabled={deleteMut.isPending}
              title={t('liveCoverage.card.delete')}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          </div>
        ) : null}
      </header>

      <div className="p-4">
        {editing ? (
          // Side-by-side edit (mirrors the composer): content 8 / media 4.
          <div className="grid gap-5 lg:grid-cols-12">
            <div className="space-y-3 lg:col-span-8">
              <Input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder={t('liveCoverage.composer.titlePlaceholder')}
                maxLength={200}
              />
              <ArticleEditor value={doc} onChange={setDoc} articleId={articleId} locale={locale} />
              <div className="flex items-center justify-end gap-2 border-t border-border pt-3">
                <Button type="button" variant="outline" size="sm" onClick={onToggleEdit}>
                  <X className="h-4 w-4" />
                  {t('articles.form.cancel')}
                </Button>
                <Button type="button" size="sm" onClick={saveEdit} disabled={updateMut.isPending}>
                  <Check className="h-4 w-4" />
                  {t('liveCoverage.card.save')}
                </Button>
              </div>
            </div>

            <div className="space-y-3 border-border lg:col-span-4 lg:border-s lg:ps-5">
              <div className="flex items-center gap-2 text-xs font-bold uppercase text-muted-foreground">
                <ImageIcon className="h-4 w-4" />
                {t('liveCoverage.composer.media')}
              </div>
              <MediaStudio staging={staging} />
              {staging.cover || staging.gallery.length > 0 || staging.videos.length > 0 ? (
                <div className="border-t border-border pt-3">
                  <p className="mb-1 text-xs font-bold uppercase text-muted-foreground">
                    {t('liveCoverage.composer.mediaPreview')}
                  </p>
                  {(() => {
                    const p = stagingToPreview(staging);
                    return <LiveUpdateMedia images={p.images} videos={p.videos} />;
                  })()}
                </div>
              ) : null}
            </div>
          </div>
        ) : (
          // Compact feed item: small thumbnail + title + a snippet of details.
          <div className="flex gap-3">
            {thumb ? (
              <div className="relative h-20 w-28 shrink-0 overflow-hidden rounded-lg border border-border bg-muted/30">
                <img src={thumb} alt="" className="h-full w-full object-cover" />
                {mediaCount > 1 ? (
                  <span className="absolute bottom-1 end-1 bg-black/65 px-1 py-0.5 text-[10px] font-bold text-white">
                    +{mediaCount - 1}
                  </span>
                ) : null}
              </div>
            ) : null}
            <div className="min-w-0 flex-1">
              {update.title ? (
                <h3 className="line-clamp-1 text-sm font-bold leading-snug">{update.title}</h3>
              ) : null}
              {snippet ? (
                <p className="line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                  {snippet}
                </p>
              ) : null}
            </div>
          </div>
        )}
      </div>
    </article>
  );
}
