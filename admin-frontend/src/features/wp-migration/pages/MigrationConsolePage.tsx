import { useState, type ReactNode } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import {
  DatabaseZap,
  PlugZap,
  CheckCircle2,
  XCircle,
  Save,
  RefreshCw,
  Plus,
  ArrowRight,
  Rocket,
  Zap,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { CategoryMappingStep } from '../components/CategoryMappingStep';
import { ImpactPreviewStep } from '../components/ImpactPreviewStep';
import { ExecutionDashboard } from '../components/ExecutionDashboard';
import { useMigrationRuns, useTestConnection, useCreateRun, useAuditRun, useQuickIncremental } from '../hooks';
import { useToast } from '@/hooks/useToast';
import type {
  ConnectionPayload,
  ConnectionTestResult,
  MigrationRun,
  WpSourceFacts,
} from '@/types/wpMigration.types';

interface FormValues {
  name: string;
  db_host: string;
  db_port: number;
  db_name: string;
  db_username: string;
  db_password: string;
  table_prefix: string;
  uploads_path: string;
}

function clean(v: FormValues): ConnectionPayload {
  return {
    name: v.name || undefined,
    db_host: v.db_host,
    db_port: Number.isFinite(v.db_port) ? v.db_port : 3306,
    db_name: v.db_name,
    db_username: v.db_username,
    db_password: v.db_password || undefined,
    table_prefix: v.table_prefix || undefined,
    uploads_path: v.uploads_path || undefined,
  };
}

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n);

function Panel({
  icon,
  title,
  hint,
  children,
}: {
  icon: ReactNode;
  title: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <section className="rounded-2xl border border-border bg-background p-5">
      <div className="mb-5 flex items-center gap-3">
        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10">
          {icon}
        </div>
        <div>
          <h3 className="text-sm font-bold">{title}</h3>
          {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

function Stat({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-xl border border-border bg-muted/30 p-4">
      <div className="text-2xl font-bold tabular-nums">{value}</div>
      <div className="mt-1 text-xs text-muted-foreground">{label}</div>
    </div>
  );
}

function ConnectionForm({
  onTest,
  onSave,
  testResult,
  testing,
  saving,
  onCancel,
}: {
  onTest: (p: ConnectionPayload) => void;
  onSave: (p: ConnectionPayload) => void;
  testResult: ConnectionTestResult | undefined;
  testing: boolean;
  saving: boolean;
  onCancel?: () => void;
}) {
  const { t } = useTranslation('wpMigration');
  const { register, handleSubmit, getValues, formState } = useForm<FormValues>({
    defaultValues: {
      name: '',
      db_host: '127.0.0.1',
      db_port: 3306,
      db_name: '',
      db_username: 'root',
      db_password: '',
      table_prefix: '',
      uploads_path: '',
    },
  });

  const submit = handleSubmit((v) => onSave(clean(v)));

  return (
    <Panel
      icon={<DatabaseZap className="h-5 w-5 text-primary" />}
      title={t('steps.connect')}
      hint={t('form.legend')}
    >
      <form onSubmit={submit} className="space-y-4" noValidate>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label={t('form.name')} {...register('name')} />
          <TextField
            label={t('form.dbHost')}
            dir="ltr"
            error={formState.errors.db_host}
            {...register('db_host', { required: true })}
          />
          <TextField
            label={t('form.dbName')}
            dir="ltr"
            error={formState.errors.db_name}
            {...register('db_name', { required: true })}
          />
          <TextField
            label={t('form.dbPort')}
            type="number"
            dir="ltr"
            {...register('db_port', { valueAsNumber: true })}
          />
          <TextField
            label={t('form.dbUser')}
            dir="ltr"
            error={formState.errors.db_username}
            {...register('db_username', { required: true })}
          />
          <TextField label={t('form.dbPassword')} type="password" dir="ltr" {...register('db_password')} />
          <TextField label={t('form.tablePrefix')} dir="ltr" {...register('table_prefix')} />
          <TextField label={t('form.uploadsPath')} dir="ltr" {...register('uploads_path')} />
        </div>

        <p className="text-xs text-muted-foreground">{t('form.readOnly')}</p>

        {testResult ? (
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <span className="inline-flex items-center gap-1 text-emerald-600">
              <CheckCircle2 className="h-4 w-4" />
              {t('test.connected')}
            </span>
            {testResult.wordpress_detected ? (
              <span className="inline-flex items-center gap-1 text-emerald-600">
                <CheckCircle2 className="h-4 w-4" />
                {t('test.wpDetected')}
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 text-destructive">
                <XCircle className="h-4 w-4" />
                {t('test.wpNotDetected')}
              </span>
            )}
            {testResult.detected_prefix ? (
              <span className="rounded-lg bg-muted px-2 py-0.5 font-mono text-xs" dir="ltr">
                {t('test.prefix', { prefix: testResult.detected_prefix })}
              </span>
            ) : null}
          </div>
        ) : null}

        <div className="flex flex-wrap items-center gap-3">
          <Button type="button" variant="outline" onClick={() => onTest(clean(getValues()))} disabled={testing}>
            <PlugZap className="h-4 w-4" />
            {testing ? t('form.testing') : t('form.test')}
          </Button>
          <Button type="submit" disabled={saving}>
            <Save className="h-4 w-4" />
            {saving ? t('form.saving') : t('form.save')}
          </Button>
          {onCancel ? (
            <Button type="button" variant="ghost" onClick={onCancel}>
              {t('form.cancel')}
            </Button>
          ) : null}
        </div>
      </form>
    </Panel>
  );
}

function AuditDashboard({
  run,
  facts,
  auditing,
  onReaudit,
  onNew,
  onContinue,
  onExecution,
}: {
  run: MigrationRun;
  facts: WpSourceFacts;
  auditing: boolean;
  onReaudit: () => void;
  onNew: () => void;
  onContinue: () => void;
  onExecution: () => void;
}) {
  const { t } = useTranslation('wpMigration');

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="text-sm text-muted-foreground">
          {run.connection.db_name} · <span className="font-mono" dir="ltr">{facts.prefix}</span> ·{' '}
          {t('audit.scannedAt')}: {new Date(facts.scanned_at).toLocaleString()}
        </div>
        <div className="flex items-center gap-2">
          {run.approved || run.status !== 'draft' ? (
            <Button variant="outline" onClick={onExecution}>
              <Rocket className="h-4 w-4" />
              {t('exec.open')}
            </Button>
          ) : null}
          <Button onClick={onContinue}>
            {t('mapping.continue')}
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
          </Button>
          <Button variant="outline" onClick={onReaudit} disabled={auditing}>
            <RefreshCw className="h-4 w-4" />
            {auditing ? t('audit.running') : t('audit.reaudit')}
          </Button>
          <Button variant="ghost" onClick={onNew}>
            <Plus className="h-4 w-4" />
            {t('audit.newConnection')}
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Stat label={t('audit.postsPublished')} value={fmt(facts.posts.published)} />
        <Stat label={t('audit.postsTotal')} value={fmt(facts.posts.total)} />
        <Stat label={t('audit.attachments')} value={fmt(facts.attachments.total)} />
        <Stat label={t('audit.featured')} value={fmt(facts.media.featured_count)} />
        <Stat label={t('audit.categoriesCount')} value={fmt(facts.categories.count)} />
        <Stat label={t('audit.seoProvider')} value={facts.seo.provider} />
        <Stat label={t('audit.guestAuthors')} value={fmt(facts.authors.guest_author_meta)} />
        <Stat label={t('audit.inlineImages')} value={fmt(facts.content.with_inline_images)} />
      </div>

      <div className="flex flex-wrap items-center gap-2 text-sm">
        {facts.encoding ? (
          facts.encoding.healthy ? (
            <span className="inline-flex items-center gap-1 rounded-lg bg-emerald-500/10 px-2.5 py-1 text-emerald-600">
              <CheckCircle2 className="h-4 w-4" />
              {t('audit.encodingHealthy')}
            </span>
          ) : (
            <span className="inline-flex items-center gap-1 rounded-lg bg-destructive/10 px-2.5 py-1 text-destructive">
              <XCircle className="h-4 w-4" />
              {t('audit.encodingIssues', {
                invalid: facts.encoding.invalid_utf8,
                mojibake: facts.encoding.suspected_mojibake,
              })}
            </span>
          )
        ) : null}

        {facts.media.uploads_path == null ? (
          <span className="inline-flex items-center gap-1 rounded-lg bg-muted px-2.5 py-1 text-muted-foreground">
            {t('audit.uploadsUnset')}
          </span>
        ) : facts.media.uploads_readable ? (
          <span className="inline-flex items-center gap-1 rounded-lg bg-emerald-500/10 px-2.5 py-1 text-emerald-600">
            <CheckCircle2 className="h-4 w-4" />
            {t('audit.uploadsReadable')}
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 rounded-lg bg-destructive/10 px-2.5 py-1 text-destructive">
            <XCircle className="h-4 w-4" />
            {t('audit.uploadsUnreadable')}
          </span>
        )}
      </div>

      <Panel icon={<DatabaseZap className="h-5 w-5 text-primary" />} title={t('audit.site')}>
        <div className="grid gap-2 text-sm sm:grid-cols-3">
          <div>
            <span className="text-muted-foreground">{t('audit.site')}: </span>
            {facts.site.name ?? '—'}
          </div>
          <div dir="ltr">
            <span className="text-muted-foreground">URL: </span>
            {facts.site.url ?? '—'}
          </div>
          <div>
            <span className="text-muted-foreground">{t('audit.language')}: </span>
            {facts.site.language ?? '—'}
          </div>
        </div>
      </Panel>

      <Panel
        icon={<DatabaseZap className="h-5 w-5 text-primary" />}
        title={t('audit.categoriesTitle')}
        hint={fmt(facts.categories.count)}
      >
        <div className="max-h-96 overflow-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-background text-xs text-muted-foreground">
              <tr>
                <th className="p-2 text-start font-medium">{t('audit.colCategory')}</th>
                <th className="p-2 text-start font-medium">{t('audit.colSlug')}</th>
                <th className="p-2 text-end font-medium">{t('audit.colDirect')}</th>
                <th className="p-2 text-end font-medium">{t('audit.colTotal')}</th>
              </tr>
            </thead>
            <tbody>
              {facts.categories.items.map((c) => (
                <tr key={c.term_taxonomy_id} className="border-t border-border">
                  <td className="p-2">{c.name}</td>
                  <td className="p-2 font-mono text-xs text-muted-foreground" dir="ltr">
                    {c.slug}
                  </td>
                  <td className="p-2 text-end tabular-nums">{fmt(c.count)}</td>
                  <td className="p-2 text-end font-semibold tabular-nums">
                    {fmt(c.total_count ?? c.count)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Panel>
    </div>
  );
}

type View = 'audit' | 'mapping' | 'preview' | 'execution';

const LIVE_STATUSES = ['running', 'paused', 'stopping', 'completed', 'failed'];

export default function MigrationConsolePage() {
  const { t } = useTranslation('wpMigration');
  const runsQ = useMigrationRuns();
  const test = useTestConnection();
  const create = useCreateRun();
  const audit = useAuditRun();
  const [showForm, setShowForm] = useState(false);
  const [view, setView] = useState<View | null>(null);
  // ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
  const quick = useQuickIncremental();
  const { success } = useToast();

  if (runsQ.isLoading) return <PageSkeleton />;
  if (runsQ.isError) return <ErrorState onRetry={() => void runsQ.refetch()} />;

  const runs = runsQ.data ?? [];
  const current: MigrationRun | null = runs[0] ?? null;

  // افتراضياً نهبط على لوحة التنفيذ متى صارت التشغيلة فعّالة/منتهية (يقبل تجاوز المُشغِّل).
  const isLive = current ? LIVE_STATUSES.includes(current.status) : false;
  const effectiveView: View = view ?? (isLive ? 'execution' : 'audit');

  const handleSave = (p: ConnectionPayload): void => {
    create.mutate(p, {
      onSuccess: (run) => {
        setShowForm(false);
        audit.mutate(run.id);
      },
    });
  };

  const showConnect = showForm || current === null;

  return (
    <div className="space-y-6 pb-12">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-xs font-medium">
            <span className="rounded-lg bg-primary/10 px-2 py-0.5 text-primary">
              {t(effectiveView === 'execution' ? 'phase.execution' : 'phase.discovery')}
            </span>
          </div>
          <h1 className="text-2xl font-bold">{t('title')}</h1>
          <p className="text-sm text-muted-foreground">{t('subtitle')}</p>
        </div>
        {/* ⚠️ TEMPORARY FEATURE — Quick Incremental Import — Remove before Production release.
            TODO(production): احذف هذا الزرّ عند إزالة الاختصار (ظاهر على كلّ شاشات المعالج). */}
        {current &&
        current.quick_incremental_enabled &&
        current.conflict_policy &&
        !['running', 'paused', 'stopping'].includes(current.status) ? (
          <Button
            onClick={() =>
              quick.mutate(current.id, {
                onSuccess: () => {
                  success(t('exec.toast.started'));
                  setView('execution');
                },
              })
            }
            disabled={quick.isPending}
          >
            <Zap className="h-4 w-4" />
            {t('exec.incremental')}
          </Button>
        ) : null}
      </header>

      {showConnect ? (
        <ConnectionForm
          onTest={(p) => test.mutate(p)}
          testResult={test.data}
          testing={test.isPending}
          onSave={handleSave}
          saving={create.isPending || audit.isPending}
          onCancel={current ? () => setShowForm(false) : undefined}
        />
      ) : current && current.source_facts ? (
        effectiveView === 'execution' ? (
          <ExecutionDashboard run={current} onBack={() => setView('audit')} />
        ) : effectiveView === 'preview' ? (
          <ImpactPreviewStep
            run={current}
            onBack={() => setView('mapping')}
            onExecution={() => setView('execution')}
          />
        ) : effectiveView === 'mapping' ? (
          <CategoryMappingStep
            runId={current.id}
            onBack={() => setView('audit')}
            onContinue={() => setView('preview')}
          />
        ) : (
          <AuditDashboard
            run={current}
            facts={current.source_facts}
            auditing={audit.isPending}
            onReaudit={() => audit.mutate(current.id)}
            onNew={() => setShowForm(true)}
            onContinue={() => setView('mapping')}
            onExecution={() => setView('execution')}
          />
        )
      ) : (
        <Panel
          icon={<DatabaseZap className="h-5 w-5 text-primary" />}
          title={t('steps.audit')}
          hint={current?.connection.db_name ?? undefined}
        >
          <p className="mb-4 text-sm text-muted-foreground">{t('audit.pending')}</p>
          <div className="flex items-center gap-3">
            <Button onClick={() => current && audit.mutate(current.id)} disabled={audit.isPending}>
              <RefreshCw className="h-4 w-4" />
              {audit.isPending ? t('audit.running') : t('audit.run')}
            </Button>
            <Button variant="ghost" onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('audit.newConnection')}
            </Button>
          </div>
        </Panel>
      )}
    </div>
  );
}
