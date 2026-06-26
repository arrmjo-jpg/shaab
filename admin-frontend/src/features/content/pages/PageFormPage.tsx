import { useEffect, useMemo, useRef } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Archive,
  ArrowRight,
  CircleDot,
  FileText,
  RotateCcw,
  Save,
  Search,
  Send,
  Settings,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { Label } from '@/components/ui/label';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { cn } from '@/lib/utils';
import { autoSlug } from '@/lib/slug';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useCreatePage,
  usePage,
  useTransitionPage,
  useUpdatePage,
} from '../pages.hooks';
import { PageContentEditor } from '../components/pages/PageContentEditor';
import { pageFormSchema, type PageFormValues } from '../pages.schemas';
import type { NormalizedError } from '@/types/api';
import type { PageStatus, PageUpsertPayload } from '@/types/content.types';

const STATUS_TONE: Record<PageStatus, 'success' | 'muted'> = {
  published: 'success',
  draft: 'muted',
  archived: 'muted',
};

// نطاقات SEO القياسية — يفضّلها محرّك بحث Google لمقتطفات النتائج.
const SEO_TITLE_RANGE = { min: 30, max: 60 };
const SEO_DESC_RANGE = { min: 70, max: 160 };

function countTone(len: number, range: { min: number; max: number }): string {
  if (len === 0) return 'text-muted-foreground';
  if (len > range.max) return 'text-destructive';
  if (len < range.min) return 'text-amber-600 dark:text-amber-400';
  return 'text-emerald-600 dark:text-emerald-400';
}

const EMPTY: PageFormValues = {
  title: '',
  locale: 'ar',
  slug: '',
  content: '',
  template: '',
  show_in_header: false,
  show_in_footer: false,
  sort_order: 0,
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  canonical_url: '',
  robots: '',
};

/** قسم بإطار موحّد مع رأس أيقونيّ — يطابق نمط بقية صفحات النماذج. */
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
        <div className="flex h-10 w-10 shrink-0 items-center justify-center bg-primary/10">
          {icon}
        </div>
        <div>
          <h2 className="text-sm font-bold">{title}</h2>
          {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

export default function PageFormPage() {
  const { t, i18n } = useTranslation('content');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const pageId = id ? Number(id) : null;
  const isEdit = pageId !== null;

  const { hasPermission } = useAuth();
  const { success, error: toastError, confirm } = useToast();

  const canCreate = hasPermission('pages.create');
  const canEdit = hasPermission('pages.edit');
  const canPublish = hasPermission('pages.publish');
  const canArchive = hasPermission('pages.archive');

  const pageQ = usePage(pageId);
  const create = useCreatePage();
  const update = useUpdatePage();
  const transition = useTransitionPage();

  const page = pageQ.data ?? null;

  const {
    register,
    handleSubmit,
    control,
    formState,
    reset,
    setError,
    setValue,
    watch,
  } = useForm<PageFormValues>({
    resolver: zodResolver(pageFormSchema),
    defaultValues: EMPTY,
  });

  // قيم مراقَبة — تُستخدم في معاينة SEO وفي توليد slug التلقائي.
  const watchedTitle = watch('title');
  const watchedSlug = watch('slug') ?? '';
  const watchedLocale = watch('locale');
  const watchedSeoTitle = watch('seo_title') ?? '';
  const watchedSeoDesc = watch('seo_description') ?? '';

  // Hydrate في وضع التعديل بمجرد اكتمال الجلب.
  useEffect(() => {
    if (!page) return;
    reset({
      title: page.title,
      locale: page.locale,
      slug: page.slug,
      content: page.content_html ?? '',
      template: page.template ?? '',
      show_in_header: page.show_in_header,
      show_in_footer: page.show_in_footer,
      sort_order: page.sort_order,
      seo_title: page.seo.title ?? '',
      seo_description: page.seo.description ?? '',
      seo_keywords: page.seo.keywords ?? '',
      canonical_url: page.seo.canonical_url ?? '',
      robots: page.seo.robots ?? '',
    });
  }, [page, reset]);

  // ── Auto-slug: في وضع الإنشاء فقط، ولّد slug من العنوان طالما المستخدم
  //    لم يلمسه يدوياً. نتتبّع آخر مُولَّد كذاكرة لتمييز "تلقائيّ" عن
  //    "كتبه المستخدم". إذا غيّر المستخدم الـ slug إلى شيء آخر، نتوقّف عن
  //    التوليد التلقائي حتى يفرغه (يعود إلى وضع التوليد).
  const lastAutoSlug = useRef<string>('');
  useEffect(() => {
    if (isEdit) return;
    const generated = autoSlug(watchedTitle ?? '');
    const slugIsAuto = watchedSlug === '' || watchedSlug === lastAutoSlug.current;
    if (!slugIsAuto) return;
    if (generated === watchedSlug) return;
    lastAutoSlug.current = generated;
    setValue('slug', generated, { shouldDirty: false, shouldValidate: false });
  }, [watchedTitle, watchedSlug, isEdit, setValue]);

  // حماية التغييرات غير المحفوظة عند تحديث/إغلاق التبويب.
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
        title: t('page.form.unsavedTitle'),
        text: t('page.form.unsavedText'),
        confirmText: t('page.form.unsavedLeave'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    )
      return;
    navigate(paths.pagesList);
  };

  // ربط أخطاء التحقّق من الـ backend (422) بحقول النموذج بدل toast عام غامض.
  const applyServerErrors = (e: NormalizedError): void => {
    const map: Partial<Record<string, keyof PageFormValues>> = {
      title: 'title',
      locale: 'locale',
      slug: 'slug',
      content: 'content',
      template: 'template',
      show_in_header: 'show_in_header',
      show_in_footer: 'show_in_footer',
      sort_order: 'sort_order',
      seo_title: 'seo_title',
      seo_description: 'seo_description',
      seo_keywords: 'seo_keywords',
      canonical_url: 'canonical_url',
      robots: 'robots',
    };
    Object.entries(e.errors ?? {}).forEach(([key, msgs]) => {
      const msg = msgs?.[0];
      const field = map[key];
      if (msg && field) setError(field, { message: msg });
    });
    if (e.message) toastError(e.message);
  };

  const submit = handleSubmit(
    (values) => {
      const payload: PageUpsertPayload = {
        title: values.title.trim(),
        locale: values.locale,
        slug: values.slug?.trim() || null,
        content: values.content,
        template: values.template?.trim() || null,
        show_in_header: values.show_in_header,
        show_in_footer: values.show_in_footer,
        sort_order: values.sort_order,
        seo_title: values.seo_title?.trim() || null,
        seo_description: values.seo_description?.trim() || null,
        seo_keywords: values.seo_keywords?.trim() || null,
        canonical_url: values.canonical_url?.trim() || null,
        robots: values.robots?.trim() || null,
      };
      const onDone = {
        onSuccess: (saved: { id: number }) => {
          success(t('page.form.saved'));
          if (!isEdit) navigate(paths.pagesEdit.replace(':id', String(saved.id)));
        },
        onError: applyServerErrors,
      };
      if (isEdit && pageId !== null) {
        update.mutate({ id: pageId, payload }, onDone);
      } else {
        create.mutate(payload, onDone);
      }
    },
    () => toastError(t('page.form.invalid')),
  );

  // ── اختصار لوحة المفاتيح: Ctrl/⌘+S للحفظ السريع من أي مكان في النموذج.
  //    يحجب سلوك المتصفّح الافتراضي (Save page) ويستدعي submit عبر RHF —
  //    التحقّق وعرض الأخطاء يحدثان كأنه ضغط زر الحفظ.
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const isSave = (e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S');
      if (!isSave) return;
      e.preventDefault();
      void submit();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [submit]);

  const doPublish = async () => {
    if (!page) return;
    if (
      await confirm({
        title: t('page.confirm.publishTitle'),
        text: t('page.confirm.publishText'),
        confirmText: t('page.action.publish'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      transition.mutate({ id: page.id, status: 'published' });
  };

  const doArchive = async () => {
    if (!page) return;
    if (
      await confirm({
        title: t('page.confirm.archiveTitle'),
        text: t('page.confirm.archiveText'),
        confirmText: t('page.action.archive'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      transition.mutate({ id: page.id, status: 'archived' });
  };

  const doDraft = async () => {
    if (!page) return;
    if (
      await confirm({
        title: t('page.confirm.draftTitle'),
        text: t('page.confirm.draftText'),
        confirmText: t('page.action.toDraft'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      transition.mutate({ id: page.id, status: 'draft' });
  };

  // حالة إذن النشاط — يمنع الإرسال في وضع الإنشاء بلا صلاحية إنشاء، أو وضع
  // التعديل بلا صلاحية تعديل.
  const canSubmit = isEdit ? canEdit : canCreate;
  const saving = create.isPending || update.isPending;
  const dateFmt = useMemo(
    () => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }),
    [i18n.language],
  );

  // معاينة snippet — تطابق ما يعرضه Google: عنوان + URL أخضر + وصف.
  const effectiveSeoTitle = watchedSeoTitle.trim() || watchedTitle.trim();
  const effectiveSeoDesc = watchedSeoDesc.trim();
  const effectiveSlug = watchedSlug.trim() || autoSlug(watchedTitle ?? '');
  const previewPath = effectiveSlug ? `/pages/${effectiveSlug}` : '';

  if (isEdit && pageQ.isLoading) return <PageSkeleton />;
  if (isEdit && pageQ.isError) return <ErrorState onRetry={() => void pageQ.refetch()} />;

  return (
    <div className="space-y-6 pb-24">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => void goBack()} type="button">
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Button>
          <div className="space-y-1">
            <h1 className="text-2xl font-bold">
              {isEdit ? t('page.form.editTitle') : t('page.form.createTitle')}
            </h1>
            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
              {page ? (
                <Badge variant={STATUS_TONE[page.status]}>{t(`page.status.${page.status}`)}</Badge>
              ) : null}
              {page?.published_at ? (
                <span>
                  {t('page.form.publishedAt')} {dateFmt.format(new Date(page.published_at))}
                </span>
              ) : null}
              {page?.updated_at ? (
                <span>
                  · {t('page.form.updatedAt')} {dateFmt.format(new Date(page.updated_at))}
                </span>
              ) : null}
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {formState.isDirty ? (
            <span
              className="inline-flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400"
              title={t('page.form.dirtyHint')}
            >
              <CircleDot className="h-3 w-3" />
              {t('page.form.dirty')}
            </span>
          ) : null}
          <Button
            onClick={() => void submit()}
            disabled={saving || !canSubmit}
            type="button"
            title={t('page.form.saveShortcut')}
          >
            <Save className="h-4 w-4" />
            {saving ? t('page.form.saving') : t('page.form.save')}
          </Button>
        </div>
      </header>

      <form onSubmit={submit} className="grid gap-6 lg:grid-cols-3" noValidate>
        {/* ─── Main column ─── */}
        <div className="space-y-6 lg:col-span-2">
          <Panel
            icon={<FileText className="h-5 w-5 text-primary" />}
            title={t('page.form.basics')}
            hint={t('page.form.basicsHint')}
          >
            <TextField
              label={t('page.form.titleLabel')}
              error={formState.errors.title}
              maxLength={200}
              autoFocus={!isEdit}
              {...register('title')}
            />
            <SelectField
              label={t('page.form.locale')}
              error={formState.errors.locale}
              options={[
                { value: 'ar', label: t('articles.locale.ar') },
                { value: 'en', label: t('articles.locale.en') },
              ]}
              {...register('locale')}
            />
            <TextField
              label={t('page.form.slug')}
              dir="ltr"
              maxLength={190}
              placeholder={t('page.form.slugPlaceholder')}
              error={formState.errors.slug}
              {...register('slug')}
            />
            {!isEdit && watchedSlug ? (
              <p className="-mt-2 text-xs text-muted-foreground">
                {t('page.form.slugAutoHint')}
              </p>
            ) : null}
          </Panel>

          <Panel
            icon={<FileText className="h-5 w-5 text-primary" />}
            title={t('page.form.contentSection')}
            hint={t('page.form.contentHint')}
          >
            <Controller
              control={control}
              name="content"
              render={({ field }) => (
                <PageContentEditor
                  value={field.value}
                  onChange={field.onChange}
                  locale={watchedLocale}
                  disabled={!canSubmit}
                />
              )}
            />
            {formState.errors.content?.message ? (
              <p className="text-xs font-medium text-destructive">
                {t(formState.errors.content.message)}
              </p>
            ) : null}
          </Panel>

          <Panel
            icon={<Search className="h-5 w-5 text-primary" />}
            title={t('page.form.seoSection')}
            hint={t('page.form.seoHint')}
          >
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="seo_title">{t('page.form.seoTitle')}</Label>
                <span
                  className={cn(
                    'text-xs font-medium tabular-nums',
                    countTone(watchedSeoTitle.length, SEO_TITLE_RANGE),
                  )}
                >
                  {watchedSeoTitle.length} / {SEO_TITLE_RANGE.max}
                </span>
              </div>
              <TextField
                id="seo_title"
                label=""
                maxLength={200}
                placeholder={watchedTitle || t('page.form.seoTitlePlaceholder')}
                error={formState.errors.seo_title as never}
                {...register('seo_title')}
              />
              <p className="text-xs text-muted-foreground">
                {t('page.form.seoTitleHint', { min: SEO_TITLE_RANGE.min, max: SEO_TITLE_RANGE.max })}
              </p>
            </div>

            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="seo_description">{t('page.form.seoDescription')}</Label>
                <span
                  className={cn(
                    'text-xs font-medium tabular-nums',
                    countTone(watchedSeoDesc.length, SEO_DESC_RANGE),
                  )}
                >
                  {watchedSeoDesc.length} / {SEO_DESC_RANGE.max}
                </span>
              </div>
              <TextareaField
                id="seo_description"
                label=""
                rows={3}
                maxLength={500}
                placeholder={t('page.form.seoDescriptionPlaceholder')}
                error={formState.errors.seo_description as never}
                {...register('seo_description')}
              />
              <p className="text-xs text-muted-foreground">
                {t('page.form.seoDescHint', { min: SEO_DESC_RANGE.min, max: SEO_DESC_RANGE.max })}
              </p>
            </div>

            <TextField
              label={t('page.form.seoKeywords')}
              maxLength={500}
              error={formState.errors.seo_keywords as never}
              {...register('seo_keywords')}
            />
            <TextField
              label={t('page.form.canonicalUrl')}
              dir="ltr"
              placeholder="https://"
              error={formState.errors.canonical_url as never}
              {...register('canonical_url')}
            />
            <TextField
              label={t('page.form.robots')}
              dir="ltr"
              placeholder="noindex, nofollow"
              error={formState.errors.robots as never}
              {...register('robots')}
            />

            {/* ── معاينة snippet — كما يظهر في نتائج Google تقريباً ── */}
            <div className="space-y-2 border-t border-border pt-4">
              <p className="text-xs font-medium text-muted-foreground">
                {t('page.form.snippetPreview')}
              </p>
              <div className="space-y-1 border border-border bg-background p-3">
                <p className="truncate text-xs text-emerald-700 dark:text-emerald-400" dir="ltr">
                  {previewPath || t('page.form.snippetPlaceholderUrl')}
                </p>
                <p className="line-clamp-1 text-base font-medium text-[#1a0dab] dark:text-[#8ab4f8]">
                  {effectiveSeoTitle || t('page.form.snippetPlaceholderTitle')}
                </p>
                <p className="line-clamp-2 text-xs text-muted-foreground">
                  {effectiveSeoDesc || t('page.form.snippetPlaceholderDesc')}
                </p>
              </div>
            </div>
          </Panel>
        </div>

        {/* ─── Sidebar ─── */}
        <div className="space-y-6">
          {isEdit && page ? (
            <Panel
              icon={<Send className="h-5 w-5 text-primary" />}
              title={t('page.form.workflow')}
              hint={t('page.form.workflowHint')}
            >
              <div className="flex flex-wrap items-center gap-2">
                <span className="text-xs text-muted-foreground">{t('page.form.currentStatus')}</span>
                <Badge variant={STATUS_TONE[page.status]}>{t(`page.status.${page.status}`)}</Badge>
              </div>
              <div className="flex flex-wrap gap-2 border-t border-border pt-3">
                {canPublish && page.status !== 'published' ? (
                  <Button variant="outline" size="sm" type="button" onClick={() => void doPublish()}>
                    <Send className="h-4 w-4" />
                    {t('page.action.publish')}
                  </Button>
                ) : null}
                {canArchive && page.status !== 'archived' ? (
                  <Button variant="outline" size="sm" type="button" onClick={() => void doArchive()}>
                    <Archive className="h-4 w-4" />
                    {t('page.action.archive')}
                  </Button>
                ) : null}
                {canEdit && page.status !== 'draft' ? (
                  <Button variant="outline" size="sm" type="button" onClick={() => void doDraft()}>
                    <RotateCcw className="h-4 w-4" />
                    {t('page.action.toDraft')}
                  </Button>
                ) : null}
              </div>
            </Panel>
          ) : null}

          <Panel
            icon={<Settings className="h-5 w-5 text-primary" />}
            title={t('page.form.display')}
            hint={t('page.form.displayHint')}
          >
            <Controller
              control={control}
              name="show_in_header"
              render={({ field }) => (
                <SwitchField
                  label={t('page.form.showInHeader')}
                  description={t('page.form.showInHeaderHint')}
                  checked={field.value}
                  onChange={field.onChange}
                />
              )}
            />
            <Controller
              control={control}
              name="show_in_footer"
              render={({ field }) => (
                <SwitchField
                  label={t('page.form.showInFooter')}
                  description={t('page.form.showInFooterHint')}
                  checked={field.value}
                  onChange={field.onChange}
                />
              )}
            />
            <div className="space-y-1.5">
              <Label htmlFor="sort_order">{t('page.form.sortOrder')}</Label>
              <input
                id="sort_order"
                type="number"
                min={0}
                max={65535}
                step={1}
                aria-invalid={Boolean(formState.errors.sort_order)}
                className={cn(
                  'flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm transition-colors',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  'aria-[invalid=true]:border-destructive',
                )}
                {...register('sort_order')}
              />
              {formState.errors.sort_order?.message ? (
                <p className="text-xs font-medium text-destructive">
                  {t(formState.errors.sort_order.message)}
                </p>
              ) : null}
            </div>
            <TextField
              label={t('page.form.template')}
              dir="ltr"
              placeholder={t('page.form.templatePlaceholder')}
              error={formState.errors.template as never}
              {...register('template')}
            />
          </Panel>
        </div>
      </form>
    </div>
  );
}
