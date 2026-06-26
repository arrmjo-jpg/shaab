import { useEffect, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, ChevronDown, Info, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { ErrorState } from '@/components/feedback';
import { OgImagePicker } from '@/features/content/components/OgImagePicker';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';
import { useToast } from '@/hooks/useToast';
import { BroadcastSourceManager } from '../components/BroadcastSourceManager';
import { useBroadcast, useBroadcastCategories, useCreateBroadcast, useUpdateBroadcast } from '../hooks';
import type {
  BroadcastKind,
  BroadcastSourceType,
  BroadcastStatus,
  BroadcastUpsertPayload,
} from '@/types/broadcast.types';

interface FormState {
  title: string;
  slug: string;
  excerpt: string;
  description: string;
  kind: BroadcastKind;
  source_type: BroadcastSourceType;
  source_url: string;
  category_id: number | null;
  vod_video_id: string;
  is_featured: boolean;
  is_public: boolean;
  scheduled_at: string;
  cover_media_id: number | null;
  cover_url: string | null;
  poster_path: string;
  seo_title: string;
  seo_description: string;
  seo_keywords: string;
  canonical_url: string;
  robots: string;
}

const EMPTY: FormState = {
  title: '',
  slug: '',
  excerpt: '',
  description: '',
  kind: 'live',
  source_type: 'hls',
  source_url: '',
  category_id: null,
  vod_video_id: '',
  is_featured: false,
  is_public: true,
  scheduled_at: '',
  cover_media_id: null,
  cover_url: null,
  poster_path: '',
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  canonical_url: '',
  robots: '',
};

const STATUS_TONE: Record<BroadcastStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  scheduled: 'muted',
  live: 'success',
  offline: 'muted',
  ended: 'muted',
  failed: 'destructive',
  archived: 'muted',
};

const KINDS: BroadcastKind[] = ['live', 'tv', 'radio'];

/** datetime-local يحتاج "YYYY-MM-DDTHH:mm" محلّياً — نحوّل ISO الوارد. */
function toLocalInput(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

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

export default function BroadcastFormPage() {
  const { t } = useTranslation('broadcast');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const broadcastId = id ? Number(id) : null;
  const isEdit = broadcastId !== null;

  const { success, confirm } = useToast();

  const [form, setForm] = useState<FormState>(EMPTY);
  const [baseline, setBaseline] = useState<string>(JSON.stringify(EMPTY));
  const [seoOpen, setSeoOpen] = useState(false);

  const q = useBroadcast(broadcastId);
  const broadcast = q.data;
  const catQ = useBroadcastCategories();
  const create = useCreateBroadcast();
  const update = useUpdateBroadcast();

  const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

  useEffect(() => {
    if (!broadcast) return;
    const hydrated: FormState = {
      title: broadcast.title,
      slug: broadcast.slug,
      excerpt: broadcast.excerpt ?? '',
      description: broadcast.description ?? '',
      kind: broadcast.kind,
      source_type: broadcast.source_type,
      source_url: broadcast.source_url,
      category_id: broadcast.category_id,
      vod_video_id: broadcast.vod_video_id == null ? '' : String(broadcast.vod_video_id),
      is_featured: broadcast.is_featured,
      is_public: broadcast.is_public,
      scheduled_at: toLocalInput(broadcast.scheduled_at),
      cover_media_id: broadcast.cover_media_id ?? null,
      cover_url: broadcast.cover_url ?? null,
      poster_path: broadcast.poster_path ?? '',
      seo_title: broadcast.seo.title ?? '',
      seo_description: broadcast.seo.description ?? '',
      seo_keywords: broadcast.seo.keywords ?? '',
      canonical_url: broadcast.seo.canonical_url ?? '',
      robots: broadcast.seo.robots ?? '',
    };
    setForm(hydrated);
    setBaseline(JSON.stringify(hydrated));
  }, [broadcast]);

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
    navigate(paths.bcBroadcasts);
  };

  const payload = (): BroadcastUpsertPayload => {
    const base: BroadcastUpsertPayload = {
      title: form.title.trim(),
      kind: form.kind,
      source_type: form.source_type,
      source_url: form.source_url.trim(),
      category_id: form.category_id,
      vod_video_id: form.vod_video_id.trim() === '' ? null : Number(form.vod_video_id),
      excerpt: form.excerpt.trim() || null,
      description: form.description.trim() || null,
      slug: form.slug.trim() || null,
      cover_media_id: form.cover_media_id,
      poster_path: form.poster_path.trim() || null,
      scheduled_at: form.scheduled_at ? new Date(form.scheduled_at).toISOString() : null,
      is_featured: form.is_featured,
      is_public: form.is_public,
      seo_title: form.seo_title.trim() || null,
      seo_description: form.seo_description.trim() || null,
      seo_keywords: form.seo_keywords.trim() || null,
      canonical_url: form.canonical_url.trim() || null,
      robots: form.robots.trim() || null,
    };
    return base;
  };

  const save = async () => {
    if (isEdit && broadcastId !== null) {
      await update.mutateAsync({ id: broadcastId, payload: payload() });
      setBaseline(JSON.stringify(form));
      success(t('form.saved'));
    } else {
      const created = await create.mutateAsync(payload());
      setBaseline(JSON.stringify(form));
      success(t('form.saved'));
      navigate(paths.bcBroadcastsEdit.replace(':id', String(created.id)));
    }
  };

  const saving = create.isPending || update.isPending;
  const hasSource = form.source_url.trim() !== '';
  const categoryOptions = catQ.data ?? [];

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
            <h1 className="text-2xl font-bold">{isEdit ? t('form.editTitle') : t('form.createTitle')}</h1>
            {broadcast ? (
              <Badge variant={STATUS_TONE[broadcast.status]} className="mt-1">
                {t(`status.${broadcast.status}`)}
              </Badge>
            ) : null}
          </div>
        </div>
        <Button onClick={() => void save()} disabled={saving || form.title.trim().length < 2 || (!isEdit && !hasSource)}>
          <Save className="h-4 w-4" />
          {saving ? t('form.saving') : t('form.save')}
        </Button>
      </header>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main column */}
        <div className="space-y-6 lg:col-span-2">
          <Section title={t('form.basics')}>
            <TextField label={t('form.titleLabel')} value={form.title} onChange={(e) => patch({ title: e.target.value })} maxLength={200} />
            <TextareaField label={t('form.descriptionLabel')} rows={5} value={form.description} onChange={(e) => patch({ description: e.target.value })} maxLength={5000} />
            <TextareaField label={t('form.excerptLabel')} rows={2} value={form.excerpt} onChange={(e) => patch({ excerpt: e.target.value })} maxLength={500} />
            <SelectField
              label={t('form.kindLabel')}
              value={form.kind}
              onChange={(e) => patch({ kind: e.target.value as BroadcastKind })}
              options={KINDS.map((k) => ({ value: k, label: t(`kind.${k}`) }))}
            />
          </Section>

          <Section title={t('form.sourceSection')}>
            <BroadcastSourceManager
              sourceType={form.source_type}
              sourceUrl={form.source_url}
              onChange={(src) => patch({ source_type: src.source_type, source_url: src.source_url })}
            />
          </Section>

          <Section title={t('form.coverSection')}>
            <OgImagePicker
              value={form.cover_url}
              label={t('form.coverLabel')}
              hint={t('form.coverHint')}
              onChange={(id) => patch({ cover_media_id: id, cover_url: id === null ? null : form.cover_url })}
            />
            <div className="border-t border-border pt-4">
              <TextField
                label={t('form.coverFallbackLabel')}
                value={form.poster_path}
                onChange={(e) => patch({ poster_path: e.target.value })}
                dir="ltr"
                maxLength={2048}
                placeholder={t('form.posterPlaceholder')}
              />
              <p className="mt-1.5 text-xs text-muted-foreground">{t('form.coverFallbackHint')}</p>
            </div>
          </Section>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {broadcast ? (
            <Section title={t('form.lifecycleSection')}>
              <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {t('form.lifecycleNote')}
              </p>
            </Section>
          ) : null}

          <Section title={t('form.scheduleSection')}>
            <label className="text-xs font-medium text-muted-foreground">{t('form.scheduledAtLabel')}</label>
            <input
              type="datetime-local"
              value={form.scheduled_at}
              onChange={(e) => patch({ scheduled_at: e.target.value })}
              className="mt-1.5 h-10 w-full border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
            <p className="mt-1.5 text-xs text-muted-foreground">{t('form.scheduledAtHint', { tz })}</p>
          </Section>

          <Section title={t('form.flagsSection')}>
            <SwitchField label={t('form.featuredLabel')} description={t('form.featuredHint')} checked={form.is_featured} onChange={(v) => patch({ is_featured: v })} />
            <SwitchField label={t('form.publicLabel')} description={t('form.publicHint')} checked={form.is_public} onChange={(v) => patch({ is_public: v })} />
          </Section>

          <Section title={t('form.categorySection')}>
            <SelectField
              label={t('form.category')}
              value={form.category_id == null ? '' : String(form.category_id)}
              onChange={(e) => patch({ category_id: e.target.value === '' ? null : Number(e.target.value) })}
              options={[
                { value: '', label: t('form.categoryNone') },
                ...categoryOptions.map((c) => ({ value: String(c.id), label: c.name })),
              ]}
            />
          </Section>

          <Section title={t('form.vodSection')}>
            <TextField
              label={t('form.vodLabel')}
              type="number"
              min={1}
              value={form.vod_video_id}
              onChange={(e) => patch({ vod_video_id: e.target.value })}
              dir="ltr"
              placeholder={t('form.vodPlaceholder')}
            />
            <p className="text-xs text-muted-foreground">{t('form.vodHint')}</p>
            {broadcast?.vod ? <p className="text-xs font-medium">{t('form.vodLinked', { title: broadcast.vod.title })}</p> : null}
          </Section>

          <Section title={t('form.slugSection')}>
            <TextField label={t('form.slugLabel')} value={form.slug} onChange={(e) => patch({ slug: e.target.value })} dir="ltr" maxLength={190} placeholder={t('form.slugPlaceholder')} />
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
              <>
                <TextField label={t('form.seoTitle')} value={form.seo_title} onChange={(e) => patch({ seo_title: e.target.value })} maxLength={255} />
                <TextareaField label={t('form.seoDescription')} rows={2} value={form.seo_description} onChange={(e) => patch({ seo_description: e.target.value })} maxLength={1000} />
                <TextField label={t('form.seoKeywords')} value={form.seo_keywords} onChange={(e) => patch({ seo_keywords: e.target.value })} maxLength={255} />
                <TextField label={t('form.canonicalUrl')} value={form.canonical_url} onChange={(e) => patch({ canonical_url: e.target.value })} dir="ltr" maxLength={255} />
                <TextField label={t('form.robots')} value={form.robots} onChange={(e) => patch({ robots: e.target.value })} dir="ltr" maxLength={50} placeholder={t('form.robotsPlaceholder')} />
              </>
            ) : (
              <p className="text-xs text-muted-foreground">{t('form.seoHint')}</p>
            )}
          </Section>
        </div>
      </div>
    </div>
  );
}
