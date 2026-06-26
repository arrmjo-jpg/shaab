import { useEffect, useMemo, useRef, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ArrowRight,
  CircleDot,
  Eye,
  EyeOff,
  FileText,
  Image as ImageIcon,
  Link2,
  Save,
  Search,
  User,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { Label } from '@/components/ui/label';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { autoSlug } from '@/lib/slug';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { PageContentEditor } from '@/features/content/components/pages/PageContentEditor';
import {
  useCreateTeamMember,
  useTeamMember,
  useTeamMembers,
  useToggleTeamMemberStatus,
  useUpdateTeamMember,
} from '../teamMembers.hooks';
import { TeamAvatarPicker } from '../components/TeamAvatarPicker';
import { teamMemberFormSchema, SOCIAL_KEYS, type TeamMemberFormValues } from '../teamMember.schemas';
import type { NormalizedError } from '@/types/api';
import type { TeamMemberStatus, TeamMemberUpsertPayload } from '@/types/team.types';

const STATUS_TONE: Record<TeamMemberStatus, 'success' | 'muted'> = {
  active: 'success',
  inactive: 'muted',
};

// نطاقات SEO القياسية (مطابقة لفورم الصفحات).
const SEO_TITLE_RANGE = { min: 30, max: 60 };
const SEO_DESC_RANGE = { min: 70, max: 160 };

function countTone(len: number, range: { min: number; max: number }): string {
  if (len === 0) return 'text-muted-foreground';
  if (len > range.max) return 'text-destructive';
  if (len < range.min) return 'text-amber-600 dark:text-amber-400';
  return 'text-emerald-600 dark:text-emerald-400';
}

const EMPTY_SOCIAL = {
  facebook: '',
  twitter_x: '',
  instagram: '',
  tiktok: '',
  linkedin: '',
  youtube: '',
  website: '',
};

const EMPTY: TeamMemberFormValues = {
  name: '',
  job_title: '',
  department: '',
  slug: '',
  bio: '',
  avatar_asset_id: null,
  social_links: { ...EMPTY_SOCIAL },
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  canonical_url: '',
  robots: '',
  status: 'active',
};

function Panel({
  icon,
  title,
  hint,
  children,
}: {
  icon: React.ReactNode;
  title: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <section className="space-y-4 border border-border bg-background p-5">
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-primary/10">{icon}</div>
        <div>
          <h2 className="text-sm font-bold">{title}</h2>
          {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

export default function TeamMemberFormPage() {
  const { t, i18n } = useTranslation('team');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const memberId = id ? Number(id) : null;
  const isEdit = memberId !== null;

  const { hasPermission } = useAuth();
  const { success, error: toastError, confirm } = useToast();

  const canCreate = hasPermission('team.create');
  const canEdit = hasPermission('team.edit');
  const canSubmit = isEdit ? canEdit : canCreate;

  const memberQ = useTeamMember(memberId);
  const create = useCreateTeamMember();
  const update = useUpdateTeamMember();
  const toggle = useToggleTeamMemberStatus();
  const member = memberQ.data ?? null;

  // datalist للأقسام — استعلام خفيف لاقتراح القيم الموجودة (combobox UX).
  const deptQ = useTeamMembers({
    page: 1,
    per_page: 100,
    search: '',
    status: '',
    department: '',
    sort: 'name',
    trashed: '',
  });
  const departments = useMemo(
    () =>
      Array.from(
        new Set((deptQ.data?.data ?? []).map((m) => m.department).filter(Boolean)),
      ) as string[],
    [deptQ.data],
  );

  const [avatarPreview, setAvatarPreview] = useState<string | null>(null);

  const { register, handleSubmit, control, formState, reset, setError, setValue, watch } =
    useForm<TeamMemberFormValues>({
      resolver: zodResolver(teamMemberFormSchema),
      defaultValues: EMPTY,
    });

  const watchedName = watch('name');
  const watchedSlug = watch('slug') ?? '';
  const watchedSeoTitle = watch('seo_title') ?? '';
  const watchedSeoDesc = watch('seo_description') ?? '';

  // Hydrate في وضع التعديل.
  useEffect(() => {
    if (!member) return;
    reset({
      name: member.name,
      job_title: member.job_title,
      department: member.department ?? '',
      slug: member.slug,
      bio: member.bio_html ?? '',
      avatar_asset_id: member.avatar_asset_id,
      social_links: { ...EMPTY_SOCIAL, ...(member.social_links ?? {}) },
      seo_title: member.seo.title ?? '',
      seo_description: member.seo.description ?? '',
      seo_keywords: member.seo.keywords ?? '',
      canonical_url: member.seo.canonical_url ?? '',
      robots: member.seo.robots ?? '',
      status: member.status,
    });
    setAvatarPreview(member.avatar?.medium ?? member.avatar?.url ?? null);
  }, [member, reset]);

  // Auto-slug من الاسم (إنشاء فقط) ما لم يلمسه المستخدم يدوياً.
  const lastAutoSlug = useRef<string>('');
  useEffect(() => {
    if (isEdit) return;
    const generated = autoSlug(watchedName ?? '');
    const slugIsAuto = watchedSlug === '' || watchedSlug === lastAutoSlug.current;
    if (!slugIsAuto || generated === watchedSlug) return;
    lastAutoSlug.current = generated;
    setValue('slug', generated, { shouldDirty: false, shouldValidate: false });
  }, [watchedName, watchedSlug, isEdit, setValue]);

  // حماية التغييرات غير المحفوظة.
  useEffect(() => {
    if (!formState.isDirty) return;
    const handler = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [formState.isDirty]);

  const goBack = async () => {
    if (
      formState.isDirty &&
      !(await confirm({
        title: t('form.unsavedTitle'),
        text: t('form.unsavedText'),
        confirmText: t('form.unsavedLeave'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    )
      return;
    navigate(paths.teamMembers);
  };

  const applyServerErrors = (e: NormalizedError): void => {
    const map: Partial<Record<string, keyof TeamMemberFormValues>> = {
      name: 'name',
      job_title: 'job_title',
      department: 'department',
      slug: 'slug',
      bio: 'bio',
      avatar_asset_id: 'avatar_asset_id',
      seo_title: 'seo_title',
      seo_description: 'seo_description',
      seo_keywords: 'seo_keywords',
      canonical_url: 'canonical_url',
      robots: 'robots',
    };
    Object.entries(e.errors ?? {}).forEach(([key, msgs]) => {
      const msg = msgs?.[0];
      if (!msg) return;
      if (key.startsWith('social_links')) {
        setError('social_links', { message: msg });
        return;
      }
      const field = map[key];
      if (field) setError(field, { message: msg });
    });
    if (e.message) toastError(e.message);
  };

  const submit = handleSubmit(
    (values) => {
      const social: Record<string, string> = {};
      SOCIAL_KEYS.forEach((k) => {
        const v = (values.social_links[k] ?? '').trim();
        if (v) social[k] = v;
      });

      const payload: TeamMemberUpsertPayload = {
        name: values.name.trim(),
        job_title: values.job_title.trim(),
        department: values.department?.trim() || null,
        slug: values.slug?.trim() || null,
        bio: values.bio?.trim() || null,
        avatar_asset_id: values.avatar_asset_id,
        social_links: social,
        seo_title: values.seo_title?.trim() || null,
        seo_description: values.seo_description?.trim() || null,
        seo_keywords: values.seo_keywords?.trim() || null,
        canonical_url: values.canonical_url?.trim() || null,
        robots: values.robots?.trim() || null,
      };
      // status يُرسَل عند الإنشاء فقط؛ في التعديل يتغيّر عبر مسار التبديل المنفصل.
      if (!isEdit) payload.status = values.status;

      const onDone = {
        onSuccess: (saved: { id: number }) => {
          success(t('form.saved'));
          if (!isEdit) navigate(paths.teamMembersEdit.replace(':id', String(saved.id)));
        },
        onError: applyServerErrors,
      };
      if (isEdit && memberId !== null) update.mutate({ id: memberId, payload }, onDone);
      else create.mutate(payload, onDone);
    },
    () => toastError(t('form.invalid')),
  );

  // Ctrl/⌘+S.
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (!((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S'))) return;
      e.preventDefault();
      void submit();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [submit]);

  const doToggleStatus = () => {
    if (!member) return;
    toggle.mutate({ id: member.id, status: member.status === 'active' ? 'inactive' : 'active' });
  };

  const saving = create.isPending || update.isPending;
  const dateFmt = useMemo(
    () => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }),
    [i18n.language],
  );

  const effectiveSeoTitle = watchedSeoTitle.trim() || watchedName.trim();
  const effectiveSeoDesc = watchedSeoDesc.trim();
  const effectiveSlug = watchedSlug.trim() || autoSlug(watchedName ?? '');
  const previewPath = effectiveSlug ? `/team/${effectiveSlug}` : '';

  if (isEdit && memberQ.isLoading) return <PageSkeleton />;
  if (isEdit && memberQ.isError) return <ErrorState onRetry={() => void memberQ.refetch()} />;

  return (
    <div className="space-y-6 pb-24">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => void goBack()} type="button">
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Button>
          <div className="space-y-1">
            <h1 className="text-2xl font-bold">
              {isEdit ? t('form.editTitle') : t('form.createTitle')}
            </h1>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              {member ? (
                <Badge variant={STATUS_TONE[member.status]}>{t(`status.${member.status}`)}</Badge>
              ) : null}
              {member?.updated_at ? (
                <span>
                  {t('form.updatedAt')} {dateFmt.format(new Date(member.updated_at))}
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {formState.isDirty ? (
            <span
              className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
              title={t('form.dirtyHint')}
            >
              <CircleDot className="h-3 w-3" />
              {t('form.dirty')}
            </span>
          ) : null}
          <Button onClick={() => void submit()} disabled={saving || !canSubmit} type="button" title={t('form.saveShortcut')}>
            <Save className="h-4 w-4" />
            {saving ? t('form.saving') : t('form.save')}
          </Button>
        </div>
      </header>

      <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3" noValidate>
        {/* ─── Main column ─── */}
        <div className="space-y-6 lg:col-span-2">
          <Panel icon={<User className="h-5 w-5 text-primary" />} title={t('form.basics')} hint={t('form.basicsHint')}>
            <TextField
              label={t('form.name')}
              error={formState.errors.name}
              maxLength={150}
              autoFocus={!isEdit}
              {...register('name')}
            />
            <TextField
              label={t('form.jobTitle')}
              error={formState.errors.job_title}
              maxLength={150}
              {...register('job_title')}
            />
            <div className="space-y-1.5">
              <Label htmlFor="department">{t('form.department')}</Label>
              <input
                id="department"
                list="team-form-departments"
                className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                placeholder={t('form.departmentPlaceholder')}
                {...register('department')}
              />
              <datalist id="team-form-departments">
                {departments.map((d) => (
                  <option key={d} value={d} />
                ))}
              </datalist>
            </div>
            <TextField
              label={t('form.slug')}
              dir="ltr"
              maxLength={190}
              placeholder={t('form.slugPlaceholder')}
              error={formState.errors.slug}
              {...register('slug')}
            />
          </Panel>

          <Panel icon={<FileText className="h-5 w-5 text-primary" />} title={t('form.bioSection')} hint={t('form.bioHint')}>
            <Controller
              control={control}
              name="bio"
              render={({ field }) => (
                <PageContentEditor
                  value={field.value ?? ''}
                  onChange={field.onChange}
                  locale="ar"
                  disabled={!canSubmit}
                />
              )}
            />
          </Panel>

          <Panel icon={<Search className="h-5 w-5 text-primary" />} title={t('form.seoSection')} hint={t('form.seoHint')}>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="seo_title">{t('form.seoTitle')}</Label>
                <span className={cn('text-xs font-medium tabular-nums', countTone(watchedSeoTitle.length, SEO_TITLE_RANGE))}>
                  {watchedSeoTitle.length} / {SEO_TITLE_RANGE.max}
                </span>
              </div>
              <TextField
                id="seo_title"
                label=""
                maxLength={200}
                placeholder={watchedName || t('form.seoTitlePlaceholder')}
                error={formState.errors.seo_title as never}
                {...register('seo_title')}
              />
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="seo_description">{t('form.seoDescription')}</Label>
                <span className={cn('text-xs font-medium tabular-nums', countTone(watchedSeoDesc.length, SEO_DESC_RANGE))}>
                  {watchedSeoDesc.length} / {SEO_DESC_RANGE.max}
                </span>
              </div>
              <TextareaField
                id="seo_description"
                label=""
                rows={3}
                maxLength={500}
                placeholder={t('form.seoDescriptionPlaceholder')}
                error={formState.errors.seo_description as never}
                {...register('seo_description')}
              />
            </div>

            <TextField label={t('form.seoKeywords')} maxLength={500} error={formState.errors.seo_keywords as never} {...register('seo_keywords')} />
            <TextField label={t('form.canonicalUrl')} dir="ltr" placeholder="https://" error={formState.errors.canonical_url as never} {...register('canonical_url')} />
            <TextField label={t('form.robots')} dir="ltr" placeholder="noindex, nofollow" error={formState.errors.robots as never} {...register('robots')} />

            <div className="space-y-2 border-t border-border pt-4">
              <p className="text-xs font-medium text-muted-foreground">{t('form.snippetPreview')}</p>
              <div className="space-y-1 border border-border bg-background p-3">
                <p className="truncate text-xs text-emerald-700 dark:text-emerald-400" dir="ltr">
                  {previewPath || t('form.snippetPlaceholderUrl')}
                </p>
                <p className="line-clamp-1 text-base font-medium text-[#1a0dab] dark:text-[#8ab4f8]">
                  {effectiveSeoTitle || t('form.snippetPlaceholderTitle')}
                </p>
                <p className="line-clamp-2 text-xs text-muted-foreground">
                  {effectiveSeoDesc || t('form.snippetPlaceholderDesc')}
                </p>
              </div>
            </div>
          </Panel>
        </div>

        {/* ─── Sidebar ─── */}
        <div className="space-y-6">
          <Panel icon={<Eye className="h-5 w-5 text-primary" />} title={t('form.statusSection')} hint={t('form.statusHint')}>
            {isEdit && member ? (
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant={STATUS_TONE[member.status]}>{t(`status.${member.status}`)}</Badge>
                {canEdit ? (
                  <Button variant="outline" size="sm" type="button" onClick={doToggleStatus} disabled={toggle.isPending}>
                    {member.status === 'active' ? (
                      <>
                        <EyeOff className="h-4 w-4" />
                        {t('action.deactivate')}
                      </>
                    ) : (
                      <>
                        <Eye className="h-4 w-4" />
                        {t('action.activate')}
                      </>
                    )}
                  </Button>
                ) : null}
              </div>
            ) : (
              <Controller
                control={control}
                name="status"
                render={({ field }) => (
                  <select
                    className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    value={field.value}
                    onChange={field.onChange}
                  >
                    <option value="active">{t('status.active')}</option>
                    <option value="inactive">{t('status.inactive')}</option>
                  </select>
                )}
              />
            )}
          </Panel>

          <Panel icon={<ImageIcon className="h-5 w-5 text-primary" />} title={t('form.avatarSection')} hint={t('form.avatarHint')}>
            <Controller
              control={control}
              name="avatar_asset_id"
              render={({ field }) => (
                <TeamAvatarPicker
                  value={avatarPreview}
                  disabled={!canSubmit}
                  onChange={(asset) => {
                    field.onChange(asset?.id ?? null);
                    setAvatarPreview(asset?.url ?? null);
                  }}
                />
              )}
            />
          </Panel>

          <Panel icon={<Link2 className="h-5 w-5 text-primary" />} title={t('form.socialSection')} hint={t('form.socialHint')}>
            <div className="space-y-3">
              {SOCIAL_KEYS.map((key) => (
                <div key={key} className="space-y-1.5">
                  <Label htmlFor={`social_${key}`}>{t(`form.social.${key}`)}</Label>
                  <input
                    id={`social_${key}`}
                    dir="ltr"
                    placeholder="https://"
                    className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    {...register(`social_links.${key}` as const)}
                  />
                </div>
              ))}
              {formState.errors.social_links ? (
                <p className="text-xs font-medium text-destructive">{t('validation.url')}</p>
              ) : null}
            </div>
          </Panel>
        </div>
      </form>
    </div>
  );
}
