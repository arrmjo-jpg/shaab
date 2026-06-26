import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  Archive,
  ArrowRight,
  CalendarClock,
  Copy,
  FileText,
  RefreshCw,
  Save,
  Send,
  Undo2,
  UploadCloud,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { SelectField } from '@/components/form/SelectField';
import { ErrorState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { APP_TZ, fmtAmmanDateTime, isoToAmmanLocalInput, toAppWallClock } from '../datetime';
import {
  useCreateEpaper,
  useDuplicateEpaper,
  useEpaper,
  useEpaperAnalytics,
  useReplaceEpaperPdf,
  useSetEpaperCover,
  useTransitionEpaper,
  useUpdateEpaper,
} from '../hooks';
import type {
  EpaperAccessLevel,
  EpaperAnalyticsData,
  EpaperBriefPoint,
  EpaperHighlight,
  EpaperInsideSection,
  EpaperLocale,
  EpaperStatus,
} from '@/types/epaper.types';

interface FormState {
  issue_number: string;
  title: string;
  subtitle: string;
  summary: string;
  slug: string;
  publication_date: string;
  locale: EpaperLocale;
  access_level: EpaperAccessLevel;
  // محرّرات الحقول التحريريّة (سطر لكل عنصر، مفصول بـ «|») — تُحلَّل عند الحفظ.
  briefText: string;
  highlightsText: string;
  insideText: string;
}

// ── تحليل/تنسيق الحقول التحريريّة (سطر لكل عنصر: «حقل | حقل | …») ──────────────
function splitLines(text: string): string[][] {
  return text
    .split('\n')
    .map((l) => l.trim())
    .filter(Boolean)
    .map((l) => l.split('|').map((p) => p.trim()));
}
function pageOf(v: string | undefined): number | null {
  const n = Number(v);
  return Number.isFinite(n) && n > 0 ? n : null;
}
function parseBrief(text: string): EpaperBriefPoint[] {
  return splitLines(text).filter((p) => p[0]).map((p) => ({ title: p[0], why: p[1] || null }));
}
function parseHighlights(text: string): EpaperHighlight[] {
  return splitLines(text).filter((p) => p[0]).map((p) => ({ title: p[0], quote: p[1] || null, page: pageOf(p[2]) }));
}
function parseInside(text: string): EpaperInsideSection[] {
  return splitLines(text).filter((p) => p[0]).map((p) => ({ label: p[0], lead: p[1] || null, page: pageOf(p[2]) }));
}
function fmtBrief(arr: EpaperBriefPoint[] | null): string {
  return (arr ?? []).map((b) => [b.title, b.why].filter(Boolean).join(' | ')).join('\n');
}
function fmtHighlights(arr: EpaperHighlight[] | null): string {
  return (arr ?? []).map((h) => [h.title, h.quote, h.page].filter((x) => x !== null && x !== undefined && x !== '').join(' | ')).join('\n');
}
function fmtInside(arr: EpaperInsideSection[] | null): string {
  return (arr ?? []).map((s) => [s.label, s.lead, s.page].filter((x) => x !== null && x !== undefined && x !== '').join(' | ')).join('\n');
}

function todayIso(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: APP_TZ }).format(new Date());
}

const EMPTY: FormState = {
  issue_number: '',
  title: '',
  subtitle: '',
  summary: '',
  slug: '',
  publication_date: todayIso(),
  locale: 'ar',
  access_level: 'public',
  briefText: '',
  highlightsText: '',
  insideText: '',
};

const STATUS_TONE: Record<EpaperStatus, 'success' | 'muted'> = {
  published: 'success',
  scheduled: 'muted',
  draft: 'muted',
  archived: 'muted',
};

const MAX_PDF_MB = 100;

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

function PdfPicker({
  file,
  onPick,
  label,
  hint,
  accept = 'application/pdf',
}: {
  file: File | null;
  onPick: (f: File | null) => void;
  label: string;
  hint: string;
  accept?: string;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  return (
    <div className="space-y-2">
      <button
        type="button"
        onClick={() => inputRef.current?.click()}
        className="flex w-full items-center gap-3 border border-dashed border-input bg-background p-4 text-start transition-colors hover:border-primary"
      >
        <span className="flex h-10 w-10 shrink-0 items-center justify-center bg-muted">
          <UploadCloud className="h-5 w-5 text-muted-foreground" />
        </span>
        <span className="min-w-0">
          <span className="block truncate text-sm font-medium">{file ? file.name : label}</span>
          <span className="block text-xs text-muted-foreground">
            {file ? `${(file.size / 1024 / 1024).toFixed(2)} MB` : hint}
          </span>
        </span>
      </button>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        onChange={(e) => onPick(e.target.files?.[0] ?? null)}
      />
    </div>
  );
}

function StatRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium tabular-nums">{value}</dd>
    </div>
  );
}

function fmtDuration(seconds: number): string {
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${String(s).padStart(2, '0')}`;
}

/** بطاقة تحليلات القارئ للقراءة فقط (Phase 5) — أساسيّة لا مؤسسيّة. */
function AnalyticsSection({ data }: { data?: EpaperAnalyticsData }) {
  const { t } = useTranslation('epaper');

  if (!data) {
    return (
      <Section title={t('analytics.title')}>
        <p className="text-xs text-muted-foreground">{t('analytics.none')}</p>
      </Section>
    );
  }

  const { totals, top_pages, top_terms } = data;

  return (
    <Section title={t('analytics.title')}>
      <dl className="space-y-2 text-sm">
        <StatRow label={t('analytics.opens')} value={totals.opens} />
        <StatRow label={t('analytics.sessions')} value={totals.sessions} />
        <StatRow label={t('analytics.avgDuration')} value={fmtDuration(totals.avg_session_seconds)} />
        <StatRow label={t('analytics.pagesViewed')} value={totals.pages_viewed} />
        <StatRow label={t('analytics.searches')} value={totals.searches} />
        <StatRow label={t('analytics.bookmarks')} value={totals.bookmarks_used} />
        <StatRow label={t('analytics.resumes')} value={totals.resumes_used} />
      </dl>

      {top_pages.length > 0 ? (
        <div className="border-t border-border pt-3">
          <h3 className="mb-1 text-xs font-bold">{t('analytics.topPages')}</h3>
          <ul className="space-y-1 text-xs text-muted-foreground">
            {top_pages.map((p) => (
              <li key={p.page} className="flex items-center justify-between">
                <span>{t('analytics.pageN', { n: p.page })}</span>
                <span className="tabular-nums">{p.views}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {top_terms.length > 0 ? (
        <div className="border-t border-border pt-3">
          <h3 className="mb-1 text-xs font-bold">{t('analytics.topTerms')}</h3>
          <ul className="space-y-1 text-xs text-muted-foreground">
            {top_terms.map((tm) => (
              <li key={tm.term} className="flex items-center justify-between gap-2">
                <span className="truncate">{tm.term}</span>
                <span className="tabular-nums">{tm.count}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </Section>
  );
}

export default function EpaperFormPage() {
  const { t, i18n } = useTranslation('epaper');
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const epaperId = id ? Number(id) : null;
  const isEdit = epaperId !== null;

  const { hasPermission } = useAuth();
  const { success, confirm } = useToast();
  const canPublish = hasPermission('epapers.publish');
  const canArchive = hasPermission('epapers.archive');
  const canEdit = hasPermission('epapers.edit');
  const canCreate = hasPermission('epapers.create');

  const [form, setForm] = useState<FormState>(EMPTY);
  const [baseline, setBaseline] = useState<string>(JSON.stringify(EMPTY));
  const [file, setFile] = useState<File | null>(null);
  const [replaceFile, setReplaceFile] = useState<File | null>(null);
  const [replaceNote, setReplaceNote] = useState('');
  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [scheduleAt, setScheduleAt] = useState('');

  const q = useEpaper(epaperId);
  const epaper = q.data;
  const create = useCreateEpaper();
  const update = useUpdateEpaper();
  const replace = useReplaceEpaperPdf();
  const setCover = useSetEpaperCover();
  const transition = useTransitionEpaper();
  const duplicate = useDuplicateEpaper();
  const analyticsQ = useEpaperAnalytics(epaperId); // مُعطَّل تلقائياً في وضع الإنشاء

  useEffect(() => {
    if (!epaper) return;
    const hydrated: FormState = {
      issue_number: String(epaper.issue_number),
      title: epaper.title,
      subtitle: epaper.subtitle ?? '',
      summary: epaper.summary ?? '',
      slug: epaper.slug,
      publication_date: epaper.publication_date ?? todayIso(),
      locale: epaper.locale,
      access_level: epaper.access_level,
      briefText: fmtBrief(epaper.brief_points),
      highlightsText: fmtHighlights(epaper.highlights),
      insideText: fmtInside(epaper.inside_this_issue),
    };
    setForm(hydrated);
    setBaseline(JSON.stringify(hydrated));
    setScheduleAt(epaper.status === 'scheduled' ? isoToAmmanLocalInput(epaper.published_at) : '');
  }, [epaper]);

  const patch = (p: Partial<FormState>) => setForm((prev) => ({ ...prev, ...p }));
  const dirty = JSON.stringify(form) !== baseline || (!isEdit && file !== null);
  const cancel = () => t('common.cancel', { ns: 'common' });

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
        cancelText: cancel(),
      }))
    )
      return;
    navigate(paths.epaperIssues);
  };

  const issueNum = Number(form.issue_number);
  const metaValid = form.title.trim().length >= 2 && issueNum >= 1 && form.publication_date !== '';
  const createValid = metaValid && file !== null && file.size <= MAX_PDF_MB * 1024 * 1024;

  const save = async () => {
    if (isEdit && epaperId !== null) {
      const brief = parseBrief(form.briefText);
      const highlights = parseHighlights(form.highlightsText);
      const inside = parseInside(form.insideText);
      await update.mutateAsync({
        id: epaperId,
        payload: {
          issue_number: issueNum,
          title: form.title.trim(),
          subtitle: form.subtitle.trim() || null,
          summary: form.summary.trim() || null,
          slug: form.slug.trim() || null,
          publication_date: form.publication_date,
          access_level: form.access_level,
          brief_points: brief.length ? brief : null,
          highlights: highlights.length ? highlights : null,
          inside_this_issue: inside.length ? inside : null,
        },
      });
      setBaseline(JSON.stringify(form));
      success(t('form.saved'));
    } else if (file) {
      const created = await create.mutateAsync({
        fields: {
          issue_number: issueNum,
          title: form.title.trim(),
          subtitle: form.subtitle.trim() || null,
          summary: form.summary.trim() || null,
          slug: form.slug.trim() || null,
          publication_date: form.publication_date,
          access_level: form.access_level,
          locale: form.locale,
        },
        file,
      });
      setBaseline(JSON.stringify(form));
      setFile(null);
      success(t('form.saved'));
      navigate(paths.epaperIssuesEdit.replace(':id', String(created.id)));
    }
  };

  const doReplace = () => {
    if (!epaper || !replaceFile) return;
    replace.mutate(
      { id: epaper.id, file: replaceFile, note: replaceNote.trim() || undefined },
      {
        onSuccess: () => {
          setReplaceFile(null);
          setReplaceNote('');
          success(t('pdfReplaced'));
        },
      },
    );
  };

  const doSetCover = () => {
    if (!epaper || !coverFile) return;
    setCover.mutate(
      { id: epaper.id, cover: coverFile },
      {
        onSuccess: () => {
          setCoverFile(null);
          success(t('coverSet'));
        },
      },
    );
  };

  const doPublish = async () => {
    if (!epaper) return;
    if (
      await confirm({
        title: t('confirm.publishTitle'),
        text: t('confirm.publishText', { title: epaper.title }),
        confirmText: t('action.publish'),
        cancelText: cancel(),
      })
    )
      transition.mutate({ id: epaper.id, status: 'published' });
  };
  const doArchive = async () => {
    if (!epaper) return;
    if (
      await confirm({
        title: t('confirm.archiveTitle'),
        text: t('confirm.archiveText', { title: epaper.title }),
        confirmText: t('action.archive'),
        cancelText: cancel(),
      })
    )
      transition.mutate({ id: epaper.id, status: 'archived' });
  };
  const doDraft = async () => {
    if (!epaper) return;
    if (
      await confirm({
        title: t('confirm.draftTitle'),
        text: t('confirm.draftText', { title: epaper.title }),
        confirmText: t('action.toDraft'),
        cancelText: cancel(),
      })
    )
      transition.mutate({ id: epaper.id, status: 'draft' });
  };
  const doSchedule = () => {
    if (!epaper || !scheduleAt) return;
    transition.mutate({ id: epaper.id, status: 'scheduled', publishedAt: toAppWallClock(scheduleAt) });
  };
  const doDuplicate = async () => {
    if (!epaper) return;
    const created = await duplicate.mutateAsync(epaper.id);
    success(t('duplicated'));
    navigate(paths.epaperIssuesEdit.replace(':id', String(created.id)));
  };

  const saving = create.isPending || update.isPending;
  const hasPdf = (epaper?.media.asset_id ?? null) !== null;

  if (isEdit && q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => void goBack()}>
            <ArrowRight className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-2xl font-bold">{isEdit ? t('form.editTitle') : t('form.createTitle')}</h1>
            {epaper ? (
              <Badge variant={STATUS_TONE[epaper.status]} className="mt-1">
                {t(`status.${epaper.status}`)}
              </Badge>
            ) : null}
          </div>
        </div>
        {canEdit || (!isEdit && canCreate) ? (
          <Button onClick={() => void save()} disabled={saving || (isEdit ? !metaValid : !createValid)}>
            <Save className="h-4 w-4" />
            {saving ? t('form.saving') : t('form.save')}
          </Button>
        ) : null}
      </header>

      <div className="grid gap-6 lg:grid-cols-3">
        {/* Main column */}
        <div className="space-y-6 lg:col-span-2">
          <Section title={t('form.basics')}>
            <div className="grid gap-4 sm:grid-cols-3">
              <TextField
                label={t('form.issueNumber')}
                type="number"
                min={1}
                value={form.issue_number}
                onChange={(e) => patch({ issue_number: e.target.value })}
              />
              <TextField
                label={t('form.publicationDate')}
                type="date"
                value={form.publication_date}
                onChange={(e) => patch({ publication_date: e.target.value })}
              />
              <SelectField
                label={t('form.locale')}
                value={form.locale}
                disabled={isEdit}
                onChange={(e) => patch({ locale: e.target.value as EpaperLocale })}
                options={[
                  { value: 'ar', label: t('locale.ar') },
                  { value: 'en', label: t('locale.en') },
                ]}
              />
            </div>

            <SelectField
              label={t('form.accessLevel')}
              value={form.access_level}
              onChange={(e) => patch({ access_level: e.target.value as EpaperAccessLevel })}
              options={[
                { value: 'public', label: t('accessLevel.public') },
                { value: 'subscriber', label: t('accessLevel.subscriber') },
                { value: 'private', label: t('accessLevel.private') },
              ]}
            />

            <TextField
              label={t('form.titleLabel')}
              value={form.title}
              onChange={(e) => patch({ title: e.target.value })}
              maxLength={190}
            />
            <TextField
              label={t('form.subtitleLabel')}
              value={form.subtitle}
              onChange={(e) => patch({ subtitle: e.target.value })}
              maxLength={190}
            />
            <TextareaField
              label={t('form.summaryLabel')}
              rows={3}
              value={form.summary}
              onChange={(e) => patch({ summary: e.target.value })}
              maxLength={2000}
            />
            <TextField
              label={t('form.slugLabel')}
              value={form.slug}
              onChange={(e) => patch({ slug: e.target.value })}
              dir="ltr"
              maxLength={180}
              placeholder={t('form.slugPlaceholder')}
            />
          </Section>

          {/* الحقول التحريريّة المنتقاة — تُحرَّر بعد إنشاء العدد (سطر لكل عنصر، مفصول بـ «|»). */}
          {isEdit ? (
            <Section title={t('form.editorial')}>
              <TextareaField
                label={t('form.briefField')}
                rows={4}
                value={form.briefText}
                onChange={(e) => patch({ briefText: e.target.value })}
                placeholder={t('form.briefHint')}
              />
              <TextareaField
                label={t('form.highlightsField')}
                rows={4}
                value={form.highlightsText}
                onChange={(e) => patch({ highlightsText: e.target.value })}
                placeholder={t('form.highlightsHint')}
              />
              <TextareaField
                label={t('form.insideField')}
                rows={4}
                value={form.insideText}
                onChange={(e) => patch({ insideText: e.target.value })}
                placeholder={t('form.insideHint')}
              />
            </Section>
          ) : null}

          {/* PDF — create: required upload; edit: replace (new version) */}
          {!isEdit ? (
            <Section title={t('form.pdfSection')}>
              <PdfPicker file={file} onPick={setFile} label={t('form.pdfPick')} hint={t('form.pdfHint', { mb: MAX_PDF_MB })} />
            </Section>
          ) : (
            <Section title={t('form.replaceSection')}>
              <p className="text-xs text-muted-foreground">{t('form.replaceHint')}</p>
              <PdfPicker
                file={replaceFile}
                onPick={setReplaceFile}
                label={t('form.replacePick')}
                hint={t('form.pdfHint', { mb: MAX_PDF_MB })}
              />
              <TextField
                label={t('form.replaceNote')}
                value={replaceNote}
                onChange={(e) => setReplaceNote(e.target.value)}
                maxLength={255}
                placeholder={t('form.replaceNotePlaceholder')}
              />
              <Button
                variant="outline"
                size="sm"
                disabled={!replaceFile || replace.isPending || !canEdit}
                onClick={doReplace}
              >
                <RefreshCw className="h-4 w-4" />
                {replace.isPending ? t('form.replacing') : t('form.replaceBtn')}
              </Button>
            </Section>
          )}

          {/* Cover image — manual upload (works without pdftoppm); shown on the public listing/hero */}
          {isEdit && epaper ? (
            <Section title={t('form.coverSection')}>
              <p className="text-xs text-muted-foreground">{t('form.coverHint')}</p>
              {epaper.media.cover_url ? (
                <img
                  src={epaper.media.cover_url}
                  alt=""
                  className="h-44 w-auto border border-border object-contain"
                />
              ) : (
                <p className="text-xs text-muted-foreground">{t('form.coverNone')}</p>
              )}
              <PdfPicker
                file={coverFile}
                onPick={setCoverFile}
                label={t('form.coverPick')}
                hint={t('form.coverPickHint')}
                accept="image/jpeg,image/png,image/webp"
              />
              <Button
                variant="outline"
                size="sm"
                disabled={!coverFile || setCover.isPending || !canEdit}
                onClick={doSetCover}
              >
                <UploadCloud className="h-4 w-4" />
                {setCover.isPending ? t('form.coverUploading') : t('form.coverBtn')}
              </Button>
            </Section>
          ) : null}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {isEdit && epaper ? (
            <>
              <Section title={t('form.fileInfo')}>
                <dl className="space-y-2 text-sm">
                  <div className="flex items-center justify-between">
                    <dt className="text-muted-foreground">{t('form.version')}</dt>
                    <dd className="font-medium tabular-nums">v{epaper.current_version}</dd>
                  </div>
                  <div className="flex items-center justify-between">
                    <dt className="text-muted-foreground">{t('form.pageCount')}</dt>
                    <dd className="font-medium tabular-nums">{epaper.page_count ?? '—'}</dd>
                  </div>
                  <div className="flex items-center justify-between gap-2">
                    <dt className="text-muted-foreground">{t('col.pdf')}</dt>
                    <dd>
                      {epaper.media.pdf_url ? (
                        <a
                          href={epaper.media.pdf_url}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-1 text-primary hover:underline"
                        >
                          <FileText className="h-3.5 w-3.5" />
                          {t('col.pdfOpen')}
                        </a>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </dd>
                  </div>
                </dl>
              </Section>

              <Section title={t('form.publishing')}>
                {!hasPdf ? (
                  <p className="flex items-start gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                    {t('form.noPdfHint')}
                  </p>
                ) : null}

                {epaper.status === 'scheduled' && epaper.published_at ? (
                  <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <CalendarClock className="h-3.5 w-3.5 shrink-0" />
                    {t('form.scheduledFor', { time: fmtAmmanDateTime(epaper.published_at, i18n.language) })}
                  </p>
                ) : null}

                <div className="flex flex-wrap gap-2">
                  {canPublish && epaper.status !== 'published' ? (
                    <Button variant="outline" size="sm" disabled={!hasPdf} onClick={() => void doPublish()}>
                      <Send className="h-4 w-4" />
                      {t('action.publish')}
                    </Button>
                  ) : null}
                  {canEdit && (epaper.status === 'published' || epaper.status === 'scheduled') ? (
                    <Button variant="outline" size="sm" onClick={() => void doDraft()}>
                      <Undo2 className="h-4 w-4" />
                      {t('action.toDraft')}
                    </Button>
                  ) : null}
                  {canArchive && epaper.status !== 'archived' ? (
                    <Button variant="outline" size="sm" onClick={() => void doArchive()}>
                      <Archive className="h-4 w-4" />
                      {t('action.archive')}
                    </Button>
                  ) : null}
                </div>

                {canPublish ? (
                  <div className="space-y-2 border-t border-border pt-3">
                    <label className="text-xs font-medium text-muted-foreground">{t('form.schedule')}</label>
                    <Input type="datetime-local" value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} />
                    <p className="flex items-center gap-1.5 text-xs font-medium">
                      <CalendarClock className="h-3.5 w-3.5" />
                      {t('schedule.tz', { tz: APP_TZ })}
                    </p>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={!scheduleAt || !hasPdf || transition.isPending}
                      onClick={doSchedule}
                    >
                      {t('form.scheduleBtn')}
                    </Button>
                  </div>
                ) : null}
              </Section>

              {canCreate ? (
                <Section title={t('form.actions')}>
                  <Button variant="outline" size="sm" disabled={duplicate.isPending} onClick={() => void doDuplicate()}>
                    <Copy className="h-4 w-4" />
                    {t('action.duplicate')}
                  </Button>
                  <p className="mt-2 text-xs text-muted-foreground">{t('form.duplicateHint')}</p>
                </Section>
              ) : null}

              <AnalyticsSection data={analyticsQ.data} />
            </>
          ) : (
            <Section title={t('form.createGuideTitle')}>
              <p className="text-xs text-muted-foreground">{t('form.createGuide')}</p>
            </Section>
          )}
        </div>
      </div>
    </div>
  );
}
