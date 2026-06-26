import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowDown, ArrowRight, ArrowUp, ChevronDown, Plus, Save, Video as VideoIcon, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { Skeleton } from '@/components/ui/skeleton';
import { ErrorState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { SeoPanel } from '@/features/content/components/SeoPanel';
import { VideoPickerModal } from '../components/VideoPickerModal';
import {
  useCreatePlaylist,
  useDetachPlaylistVideo,
  usePlaylist,
  useReorderPlaylistVideos,
  useUpdatePlaylist,
} from '../hooks';
import type { ContentLocale } from '@/types/content.types';
import type { VideoPlaylistUpsertPayload, VideoStatus, VideoVisibility } from '@/types/videoLibrary.types';

interface FormState {
  title: string;
  locale: ContentLocale;
  status: VideoStatus;
  visibility: VideoVisibility;
  is_featured: boolean;
  description: string;
  slug: string;
  seo_title: string;
  seo_description: string;
  seo_keywords: string;
  canonical_url: string;
  robots: string;
}

const EMPTY: FormState = {
  title: '',
  locale: 'ar',
  status: 'draft',
  visibility: 'public',
  is_featured: false,
  description: '',
  slug: '',
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  canonical_url: '',
  robots: '',
};

const STATUS_TONE: Record<VideoStatus, 'success' | 'muted'> = {
  published: 'success',
  scheduled: 'muted',
  draft: 'muted',
  archived: 'muted',
  submitted: 'muted',
  in_review: 'muted',
  rejected: 'muted',
};

function Section({ title, children, action }: { title: string; children: ReactNode; action?: ReactNode }) {
  return (
    <section className="space-y-4 border border-border bg-background p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-bold">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  );
}

export default function PlaylistFormPage() {
  const { t } = useTranslation('videoLibrary');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const playlistId = id ? Number(id) : null;
  const isEdit = playlistId !== null;

  const { hasPermission } = useAuth();
  const { success, confirm } = useToast();
  const canManage = hasPermission('video-playlists.manage');

  const [form, setForm] = useState<FormState>(EMPTY);
  const [baseline, setBaseline] = useState(JSON.stringify(EMPTY));
  const [seoOpen, setSeoOpen] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);

  const q = usePlaylist(playlistId);
  const playlist = q.data;
  const create = useCreatePlaylist();
  const update = useUpdatePlaylist();
  const detach = useDetachPlaylistVideo();
  const reorder = useReorderPlaylistVideos();

  useEffect(() => {
    if (!playlist) return;
    const hydrated: FormState = {
      title: playlist.title,
      locale: playlist.locale,
      status: playlist.status,
      visibility: playlist.visibility,
      is_featured: playlist.is_featured,
      description: playlist.description ?? '',
      slug: playlist.slug,
      seo_title: playlist.seo.title ?? '',
      seo_description: playlist.seo.description ?? '',
      seo_keywords: playlist.seo.keywords ?? '',
      canonical_url: playlist.seo.canonical_url ?? '',
      robots: playlist.seo.robots ?? '',
    };
    setForm(hydrated);
    setBaseline(JSON.stringify(hydrated));
  }, [playlist]);

  const patch = (p: Partial<FormState>) => setForm((prev) => ({ ...prev, ...p }));
  const dirty = JSON.stringify(form) !== baseline;

  useEffect(() => {
    if (!dirty) return;
    const handler = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  const goBack = async () => {
    if (
      dirty &&
      !(await confirm({
        title: t('form.unsavedTitle'),
        text: t('form.unsavedText'),
        confirmText: t('form.unsavedLeave'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    )
      return;
    navigate(paths.vlPlaylists);
  };

  const payload = (): VideoPlaylistUpsertPayload => ({
    title: form.title.trim(),
    locale: form.locale,
    status: form.status,
    visibility: form.visibility,
    is_featured: form.is_featured,
    description: form.description.trim() || null,
    slug: form.slug.trim() || null,
    seo_title: form.seo_title.trim() || null,
    seo_description: form.seo_description.trim() || null,
    seo_keywords: form.seo_keywords.trim() || null,
    canonical_url: form.canonical_url.trim() || null,
    robots: form.robots.trim() || null,
  });

  const save = async () => {
    if (isEdit && playlistId !== null) {
      await update.mutateAsync({ id: playlistId, payload: payload() });
      setBaseline(JSON.stringify(form));
      success(t('playlists.form.saved'));
    } else {
      const created = await create.mutateAsync(payload());
      setBaseline(JSON.stringify(form));
      success(t('playlists.form.saved'));
      navigate(paths.vlPlaylistsEdit.replace(':id', String(created.id)));
    }
  };

  const videos = playlist?.videos ?? [];
  const orderedIds = videos.map((v) => v.id);
  const moveVideo = (index: number, dir: 'up' | 'down') => {
    if (!playlistId) return;
    const target = dir === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= orderedIds.length) return;
    const next = [...orderedIds];
    [next[index], next[target]] = [next[target], next[index]];
    reorder.mutate({ id: playlistId, orderedIds: next });
  };

  const saving = create.isPending || update.isPending;

  if (isEdit && q.isError) {
    return <ErrorState onRetry={() => void q.refetch()} />;
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => void goBack()}>
            <ArrowRight className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-2xl font-bold">{isEdit ? t('playlists.form.editTitle') : t('playlists.form.createTitle')}</h1>
            {playlist ? (
              <Badge variant={STATUS_TONE[playlist.status]} className="mt-1">
                {t(`status.${playlist.status}`)}
              </Badge>
            ) : null}
          </div>
        </div>
        <Button onClick={() => void save()} disabled={!canManage || saving || form.title.trim().length < 2}>
          <Save className="h-4 w-4" />
          {saving ? t('form.saving') : t('form.save')}
        </Button>
      </header>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Videos manager */}
        <div className="space-y-6 lg:col-span-2">
          <Section
            title={t('playlists.videos.title')}
            action={
              isEdit && canManage ? (
                <Button variant="outline" size="sm" onClick={() => setPickerOpen(true)}>
                  <Plus className="h-4 w-4" />
                  {t('playlists.videos.add')}
                </Button>
              ) : null
            }
          >
            {!isEdit ? (
              <p className="py-8 text-center text-sm text-muted-foreground">{t('playlists.videos.saveFirst')}</p>
            ) : q.isLoading ? (
              <div className="space-y-2">
                {Array.from({ length: 3 }).map((_, i) => (
                  <Skeleton key={i} className="h-16 w-full" />
                ))}
              </div>
            ) : videos.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">{t('playlists.videos.empty')}</p>
            ) : (
              <ol className="space-y-2">
                {videos.map((v, i) => (
                  <li key={v.id} className="flex items-center gap-3 border border-border bg-background p-2">
                    <span className="w-6 shrink-0 text-center text-xs font-semibold tabular-nums text-muted-foreground">{i + 1}</span>
                    <span className="flex h-12 w-9 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted">
                      {v.share_image ?? v.media?.poster_url ? (
                        <img src={(v.share_image ?? v.media?.poster_url) as string} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <VideoIcon className="h-4 w-4 text-muted-foreground" />
                      )}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium">{v.title}</span>
                      <Badge variant={STATUS_TONE[v.status]} className="mt-0.5">{t(`status.${v.status}`)}</Badge>
                    </span>
                    {canManage ? (
                      <span className="flex shrink-0 items-center gap-1">
                        <button
                          type="button"
                          onClick={() => moveVideo(i, 'up')}
                          disabled={i === 0 || reorder.isPending}
                          className="flex h-7 w-7 items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-30"
                          title={t('categories.moveUp')}
                        >
                          <ArrowUp className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => moveVideo(i, 'down')}
                          disabled={i === videos.length - 1 || reorder.isPending}
                          className="flex h-7 w-7 items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-30"
                          title={t('categories.moveDown')}
                        >
                          <ArrowDown className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={() => playlistId && detach.mutate({ id: playlistId, videoId: v.id })}
                          className="flex h-7 w-7 items-center justify-center text-destructive hover:bg-destructive/10"
                          title={t('playlists.videos.remove')}
                        >
                          <X className="h-4 w-4" />
                        </button>
                      </span>
                    ) : null}
                  </li>
                ))}
              </ol>
            )}
          </Section>
        </div>

        {/* Metadata */}
        <div className="space-y-6">
          <Section title={t('playlists.form.basics')}>
            <TextField label={t('playlists.form.titleLabel')} value={form.title} onChange={(e) => patch({ title: e.target.value })} maxLength={200} />
            <TextareaField label={t('playlists.form.descriptionLabel')} rows={3} value={form.description} onChange={(e) => patch({ description: e.target.value })} maxLength={5000} />
            <SelectField
              label={t('form.locale')}
              value={form.locale}
              onChange={(e) => patch({ locale: e.target.value as ContentLocale })}
              options={[
                { value: 'ar', label: t('locale.ar') },
                { value: 'en', label: t('locale.en') },
              ]}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectField
                label={t('playlists.form.status')}
                value={form.status}
                onChange={(e) => patch({ status: e.target.value as VideoStatus })}
                options={[
                  { value: 'draft', label: t('status.draft') },
                  { value: 'published', label: t('status.published') },
                  { value: 'archived', label: t('status.archived') },
                ]}
              />
              <SelectField
                label={t('form.visibility')}
                value={form.visibility}
                onChange={(e) => patch({ visibility: e.target.value as VideoVisibility })}
                options={[
                  { value: 'public', label: t('visibility.public') },
                  { value: 'unlisted', label: t('visibility.unlisted') },
                  { value: 'private', label: t('visibility.private') },
                ]}
              />
            </div>
            <SwitchField label={t('playlists.form.featured')} description={t('playlists.form.featuredHint')} checked={form.is_featured} onChange={(v) => patch({ is_featured: v })} />
          </Section>

          <Section title={t('form.slugSection')}>
            <TextField label={t('form.slugLabel')} value={form.slug} onChange={(e) => patch({ slug: e.target.value })} dir="ltr" maxLength={190} placeholder={t('form.slugPlaceholder')} />
            <p className="text-xs text-muted-foreground">{t('playlists.form.slugHint')}</p>
          </Section>

          <Section
            title={t('form.seoSection')}
            action={
              <button type="button" onClick={() => setSeoOpen((v) => !v)} className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                {seoOpen ? t('form.seoCollapse') : t('form.seoExpand')}
                <ChevronDown className={cn('h-4 w-4 transition-transform', seoOpen && 'rotate-180')} />
              </button>
            }
          >
            {seoOpen ? (
              <SeoPanel
                title={form.title}
                seoTitle={form.seo_title}
                seoDescription={form.seo_description}
                seoKeywords={form.seo_keywords}
                canonicalUrl={form.canonical_url}
                robots={form.robots}
                excerpt={form.description}
                slug={form.slug}
                coverUrl={playlist?.cover_url ?? null}
                locale={form.locale}
                sharePath={playlist?.canonical_path ?? null}
                onChange={(p) => {
                  if (p.seoTitle !== undefined) patch({ seo_title: p.seoTitle });
                  if (p.seoDescription !== undefined) patch({ seo_description: p.seoDescription });
                  if (p.seoKeywords !== undefined) patch({ seo_keywords: p.seoKeywords });
                  if (p.canonicalUrl !== undefined) patch({ canonical_url: p.canonicalUrl });
                  if (p.robots !== undefined) patch({ robots: p.robots });
                }}
              />
            ) : (
              <p className="text-xs text-muted-foreground">{t('form.seoHint')}</p>
            )}
          </Section>
        </div>
      </div>

      {isEdit && playlistId !== null ? (
        <VideoPickerModal
          open={pickerOpen}
          onClose={() => setPickerOpen(false)}
          playlistId={playlistId}
          attachedIds={new Set(orderedIds)}
        />
      ) : null}
    </div>
  );
}
