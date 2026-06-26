import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Play,
  Pause,
  Square,
  RotateCcw,
  RefreshCw,
  ArrowRight,
  AlertTriangle,
  CheckCircle2,
  Clock,
  Gauge,
  Image as ImageIcon,
  ListChecks,
  Eye,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { useToast } from '@/hooks/useToast';
import {
  useMigrationStats,
  useMigrationReport,
  useMigrationItems,
  useStartRun,
  usePauseRun,
  useResumeRun,
  useStopRun,
  useRetryItems,
} from '../hooks';
import type {
  MigrationItemRow,
  MigrationItemStatus,
  MigrationRun,
  MigrationRunStatus,
} from '@/types/wpMigration.types';

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n);
const FILTERS: MigrationItemStatus[] = ['failed', 'partial', 'skipped', 'processing'];
const ACTIVE: MigrationRunStatus[] = ['running', 'paused', 'stopping'];

const STATUS_TONE: Record<MigrationRunStatus, string> = {
  draft: 'bg-muted text-muted-foreground',
  ready: 'bg-primary/10 text-primary',
  running: 'bg-blue-500/10 text-blue-600',
  paused: 'bg-amber-500/10 text-amber-600',
  stopping: 'bg-amber-500/10 text-amber-600',
  completed: 'bg-emerald-500/10 text-emerald-600',
  failed: 'bg-destructive/10 text-destructive',
};

function fmtDuration(seconds: number | null): string {
  if (seconds == null || seconds <= 0) return '—';
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  const parts: string[] = [];
  if (h > 0) parts.push(`${h}h`);
  if (m > 0 || h > 0) parts.push(`${m}m`);
  parts.push(`${s}s`);
  return parts.join(' ');
}

function Metric({ label, value, tone }: { label: string; value: ReactNode; tone?: string }) {
  return (
    <div className={`border border-border bg-background p-4 ${tone ?? ''}`}>
      <div className="text-2xl font-bold tabular-nums">{value}</div>
      <div className="mt-1 text-xs text-muted-foreground">{label}</div>
    </div>
  );
}

function Section({ icon, title, children }: { icon: ReactNode; title: string; children: ReactNode }) {
  return (
    <section className="border border-border bg-background p-5">
      <div className="mb-4 flex items-center gap-2">
        {icon}
        <h3 className="text-sm font-bold">{title}</h3>
      </div>
      {children}
    </section>
  );
}

export function ExecutionDashboard({ run, onBack }: { run: MigrationRun; onBack: () => void }) {
  const { t } = useTranslation('wpMigration');
  const { success, confirm } = useToast();

  const statsQ = useMigrationStats(run.id);
  const start = useStartRun();
  const pause = usePauseRun();
  const resume = useResumeRun();
  const stop = useStopRun();
  const retry = useRetryItems(run.id);

  const [filter, setFilter] = useState<MigrationItemStatus>('failed');
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<number[]>([]);
  const [detail, setDetail] = useState<MigrationItemRow | null>(null);
  const [incremental, setIncremental] = useState(false);

  const stats = statsQ.data;
  const status: MigrationRunStatus = stats?.status ?? run.status;
  const isActive = ACTIVE.includes(status);
  const isCompleted = status === 'completed';
  const busy = start.isPending || pause.isPending || resume.isPending || stop.isPending;

  const itemsQ = useMigrationItems(run.id, filter, page);
  const reportQ = useMigrationReport(run.id, isCompleted);

  const counts = stats?.counts;
  const perf = stats?.performance;
  const media = stats?.media;

  const changeFilter = (f: MigrationItemStatus): void => {
    setFilter(f);
    setPage(1);
    setSelected([]);
  };

  const toggleSelect = (id: number): void =>
    setSelected((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));

  const doRetry = (mode: 'failed' | 'partial' | 'selected'): void => {
    const payload = mode === 'selected' ? { mode, ids: selected } : { mode };
    retry.mutate(payload, {
      onSuccess: () => {
        success(t('exec.toast.retried'));
        setSelected([]);
      },
    });
  };

  const confirmStop = async (): Promise<void> => {
    const ok = await confirm({
      title: t('exec.confirmStop.title'),
      text: t('exec.confirmStop.text'),
      confirmText: t('exec.stop'),
      cancelText: t('form.cancel'),
    });
    if (ok) stop.mutate(run.id, { onSuccess: () => success(t('exec.toast.stopped')) });
  };

  const selectable = filter !== 'processing';

  return (
    <div className="space-y-5">
      {/* ── Header + controls ── */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="outline" size="sm" onClick={onBack}>
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            {t('exec.back')}
          </Button>
          <div>
            <h2 className="text-xl font-bold">{t('exec.title')}</h2>
            <p className="text-sm text-muted-foreground">{t('exec.subtitle')}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className={`px-2.5 py-1 text-xs font-bold ${STATUS_TONE[status]}`}>
            {t(`exec.status.${status}`)}
          </span>

          {!isActive ? (
            <label
              className="flex cursor-pointer items-center gap-1.5 text-xs font-medium text-muted-foreground"
              title={t('exec.incrementalHint')}
            >
              <input
                type="checkbox"
                className="accent-primary"
                checked={incremental}
                onChange={(e) => setIncremental(e.target.checked)}
              />
              {t('exec.incremental')}
            </label>
          ) : null}

          {!isActive ? (
            <Button
              onClick={() =>
                start.mutate(
                  { id: run.id, incremental },
                  { onSuccess: () => success(t('exec.toast.started')) },
                )
              }
              disabled={!run.can_execute || busy}
            >
              {isCompleted || status === 'failed' ? (
                <RotateCcw className="h-4 w-4" />
              ) : (
                <Play className="h-4 w-4" />
              )}
              {isCompleted || status === 'failed' ? t('exec.restart') : t('exec.start')}
            </Button>
          ) : null}

          {status === 'running' ? (
            <Button
              variant="outline"
              onClick={() => pause.mutate(run.id, { onSuccess: () => success(t('exec.toast.paused')) })}
              disabled={busy}
            >
              <Pause className="h-4 w-4" />
              {t('exec.pause')}
            </Button>
          ) : null}

          {status === 'paused' || status === 'stopping' ? (
            <Button
              onClick={() => resume.mutate(run.id, { onSuccess: () => success(t('exec.toast.resumed')) })}
              disabled={busy}
            >
              <Play className="h-4 w-4" />
              {t('exec.resume')}
            </Button>
          ) : null}

          {status === 'running' || status === 'paused' ? (
            <Button variant="destructive" onClick={() => void confirmStop()} disabled={busy}>
              <Square className="h-4 w-4" />
              {t('exec.stop')}
            </Button>
          ) : null}
        </div>
      </div>

      {!run.can_execute && !isActive ? (
        <div className="flex items-center gap-2 border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm font-medium text-amber-600">
          <AlertTriangle className="h-4 w-4" />
          {t('exec.needApproval')}
        </div>
      ) : null}

      {/* ── Progress bar ── */}
      <div>
        <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
          <span>{t('exec.progressLabel', { percent: perf?.percent ?? 0 })}</span>
          <span className="tabular-nums">
            {fmt(counts ? counts.done + counts.partial + counts.failed + counts.skipped : 0)} /{' '}
            {fmt(counts?.total ?? 0)}
          </span>
        </div>
        <div className="h-2 w-full overflow-hidden bg-muted">
          <div
            className="h-full bg-primary transition-all"
            style={{ width: `${perf?.percent ?? 0}%` }}
          />
        </div>
      </div>

      {/* ── Observability counts (#1) ── */}
      <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <Metric label={t('exec.count.total')} value={fmt(counts?.total ?? 0)} />
        <Metric label={t('exec.count.pending')} value={fmt(counts?.pending ?? 0)} />
        <Metric label={t('exec.count.queued')} value={fmt(counts?.queued ?? 0)} tone="bg-indigo-500/5" />
        <Metric
          label={t('exec.count.processing')}
          value={fmt(counts?.processing ?? 0)}
          tone="border-blue-500/40 bg-blue-500/5"
        />
        <Metric
          label={t('exec.count.done')}
          value={fmt(counts?.done ?? 0)}
          tone="border-emerald-500/40 bg-emerald-500/5"
        />
        <Metric
          label={t('exec.count.partial')}
          value={fmt(counts?.partial ?? 0)}
          tone={counts && counts.partial > 0 ? 'border-amber-500/40 bg-amber-500/5' : undefined}
        />
        <Metric
          label={t('exec.count.failed')}
          value={fmt(counts?.failed ?? 0)}
          tone={counts && counts.failed > 0 ? 'border-destructive/40 bg-destructive/5' : undefined}
        />
        <Metric label={t('exec.count.skipped')} value={fmt(counts?.skipped ?? 0)} tone="bg-muted/40" />
      </div>

      {/* ── Performance (#2) + Media (#3) ── */}
      <div className="grid gap-4 lg:grid-cols-2">
        <Section icon={<Gauge className="h-4 w-4 text-primary" />} title={t('exec.perf.title')}>
          <div className="grid gap-3 sm:grid-cols-3">
            <Metric label={t('exec.perf.elapsed')} value={fmtDuration(perf?.elapsed_seconds ?? 0)} />
            <Metric
              label={t('exec.perf.throughput')}
              value={`${perf?.throughput_per_min ?? 0} ${t('exec.perf.perMin')}`}
            />
            <Metric label={t('exec.perf.eta')} value={fmtDuration(perf?.eta_seconds ?? null)} />
          </div>
        </Section>

        <Section icon={<ImageIcon className="h-4 w-4 text-primary" />} title={t('exec.media.title')}>
          <div className="grid gap-3 sm:grid-cols-3">
            <Metric label={t('exec.media.imported')} value={fmt(media?.imported ?? 0)} />
            <Metric label={t('exec.media.reused')} value={fmt(media?.reused ?? 0)} />
            <Metric
              label={t('exec.media.failed')}
              value={fmt(media?.failed ?? 0)}
              tone={media && media.failed > 0 ? 'border-destructive/40 bg-destructive/5' : undefined}
            />
          </div>
        </Section>
      </div>

      {/* ── Final report (#7) ── */}
      {isCompleted && reportQ.data ? (
        <Section icon={<CheckCircle2 className="h-4 w-4 text-emerald-600" />} title={t('exec.report.title')}>
          <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <Metric
              label={t('exec.report.successRate')}
              value={`${reportQ.data.success_rate}%`}
              tone="border-emerald-500/40 bg-emerald-500/5"
            />
            <Metric
              label={t('exec.report.succeeded')}
              value={`${fmt(reportQ.data.succeeded)} / ${fmt(reportQ.data.counts.total)}`}
            />
            <Metric label={t('exec.perf.elapsed')} value={fmtDuration(reportQ.data.duration_seconds)} />
            <Metric label={t('exec.count.failed')} value={fmt(reportQ.data.counts.failed)} />
          </div>
          {reportQ.data.failures.length > 0 ? (
            <div className="mt-4">
              <div className="mb-2 text-xs font-semibold text-muted-foreground">
                {t('exec.report.failuresByReason')}
              </div>
              <div className="flex flex-wrap gap-2">
                {reportQ.data.failures.map((f) => (
                  <span key={f.reason} className="bg-muted px-2.5 py-1 text-xs">
                    {f.reason} · <span className="font-bold tabular-nums">{fmt(f.count)}</span>
                  </span>
                ))}
              </div>
            </div>
          ) : (
            <p className="mt-3 text-sm text-emerald-600">{t('exec.report.noFailures')}</p>
          )}
        </Section>
      ) : null}

      {/* ── Failure inspection (#4/#5) + retry (#6) ── */}
      <Section icon={<ListChecks className="h-4 w-4 text-primary" />} title={t('exec.failures.title')}>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-1">
            {FILTERS.map((f) => (
              <button
                key={f}
                type="button"
                onClick={() => changeFilter(f)}
                className={`px-3 py-1.5 text-xs font-semibold transition-colors ${
                  filter === f ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-accent'
                }`}
              >
                {t(`exec.filter.${f}`)}
                {counts ? <span className="ms-1 tabular-nums opacity-70">{fmt(counts[f])}</span> : null}
              </button>
            ))}
          </div>
          <div className="flex flex-wrap items-center gap-2">
            {selected.length > 0 ? (
              <Button size="sm" onClick={() => doRetry('selected')} disabled={retry.isPending}>
                <RotateCcw className="h-4 w-4" />
                {t('exec.retry.selected', { n: selected.length })}
              </Button>
            ) : null}
            {counts && counts.failed > 0 ? (
              <Button size="sm" variant="outline" onClick={() => doRetry('failed')} disabled={retry.isPending}>
                <RotateCcw className="h-4 w-4" />
                {t('exec.retry.failed')}
              </Button>
            ) : null}
            {counts && counts.partial > 0 ? (
              <Button size="sm" variant="outline" onClick={() => doRetry('partial')} disabled={retry.isPending}>
                <RotateCcw className="h-4 w-4" />
                {t('exec.retry.partial')}
              </Button>
            ) : null}
            <Button size="sm" variant="ghost" onClick={() => void itemsQ.refetch()} disabled={itemsQ.isFetching}>
              <RefreshCw className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div className="overflow-auto">
          <table className="w-full text-sm">
            <thead className="text-xs text-muted-foreground">
              <tr className="border-b border-border">
                {selectable ? <th className="w-8 p-2" /> : null}
                <th className="p-2 text-start font-medium">{t('exec.col.post')}</th>
                <th className="p-2 text-start font-medium">{t('exec.col.title')}</th>
                <th className="p-2 text-start font-medium">{t('exec.col.reason')}</th>
                <th className="p-2 text-end font-medium">{t('exec.col.attempts')}</th>
                <th className="p-2 text-end font-medium">{t('exec.col.updated')}</th>
                <th className="w-10 p-2" />
              </tr>
            </thead>
            <tbody>
              {(itemsQ.data?.items ?? []).map((it) => (
                <tr key={it.id} className="border-b border-border/60 hover:bg-accent/30">
                  {selectable ? (
                    <td className="p-2">
                      <input
                        type="checkbox"
                        className="accent-primary"
                        checked={selected.includes(it.id)}
                        onChange={() => toggleSelect(it.id)}
                      />
                    </td>
                  ) : null}
                  <td className="p-2 font-mono text-xs tabular-nums" dir="ltr">
                    {it.wp_post_id}
                  </td>
                  <td className="p-2">{it.source_title || t('exec.untitled')}</td>
                  <td className="p-2">
                    {it.failure_reason ? (
                      <span className="bg-destructive/10 px-1.5 py-0.5 text-xs text-destructive">
                        {it.failure_reason}
                      </span>
                    ) : (
                      <span className="text-xs text-muted-foreground">{t(`exec.itemStatus.${it.status}`)}</span>
                    )}
                  </td>
                  <td className="p-2 text-end tabular-nums">{it.attempts}</td>
                  <td className="p-2 text-end text-xs text-muted-foreground" dir="ltr">
                    {it.updated_at ? new Date(it.updated_at).toLocaleString() : '—'}
                  </td>
                  <td className="p-2 text-end">
                    <button
                      type="button"
                      onClick={() => setDetail(it)}
                      className="text-muted-foreground hover:text-primary"
                      aria-label={t('exec.detail.view')}
                    >
                      <Eye className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
              {(itemsQ.data?.items.length ?? 0) === 0 ? (
                <tr>
                  <td colSpan={7} className="p-6 text-center text-sm text-muted-foreground">
                    {t('exec.failures.empty')}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        {itemsQ.data && itemsQ.data.pagination.total_pages > 1 ? (
          <div className="mt-4 flex items-center justify-between text-sm">
            <span className="text-muted-foreground">
              {t('exec.page', {
                page: itemsQ.data.pagination.current_page,
                total: itemsQ.data.pagination.total_pages,
              })}
            </span>
            <div className="flex items-center gap-2">
              <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                {t('exec.prev')}
              </Button>
              <Button
                size="sm"
                variant="outline"
                disabled={page >= itemsQ.data.pagination.total_pages}
                onClick={() => setPage((p) => p + 1)}
              >
                {t('exec.next')}
              </Button>
            </div>
          </div>
        ) : null}
      </Section>

      {/* ── Timeline (#8) ── */}
      <Section icon={<Clock className="h-4 w-4 text-primary" />} title={t('exec.timeline.title')}>
        {stats && stats.timeline.length > 0 ? (
          <ol className="space-y-2">
            {stats.timeline.map((e, i) => (
              <li key={`${e.event}-${i}`} className="flex items-center gap-3 text-sm">
                <span className="h-2 w-2 shrink-0 rounded-full bg-primary" />
                <span className="font-medium">{t(`exec.event.${e.event}`, e.event)}</span>
                <span className="text-xs text-muted-foreground" dir="ltr">
                  {new Date(e.at).toLocaleString()}
                </span>
              </li>
            ))}
          </ol>
        ) : (
          <p className="text-sm text-muted-foreground">{t('exec.timeline.empty')}</p>
        )}
      </Section>

      {/* ── Per-item drill-down (#4) ── */}
      <Modal
        open={detail !== null}
        onClose={() => setDetail(null)}
        title={detail ? `#${detail.wp_post_id} · ${detail.source_title || t('exec.untitled')}` : ''}
        size="lg"
      >
        {detail ? (
          <dl className="space-y-3 text-sm">
            <div className="grid grid-cols-2 gap-3">
              <Field label={t('exec.detail.status')} value={t(`exec.itemStatus.${detail.status}`)} />
              <Field label={t('exec.detail.attempts')} value={String(detail.attempts)} />
              <Field label={t('exec.detail.reason')} value={detail.failure_reason ?? '—'} />
              <Field label={t('exec.detail.lastStep')} value={detail.last_step ?? '—'} />
              <Field
                label={t('exec.detail.article')}
                value={detail.article_id ? `#${detail.article_id}` : '—'}
              />
              <Field
                label={t('exec.media.title')}
                value={`${fmt(detail.media.imported)} / ${fmt(detail.media.reused)} / ${fmt(detail.media.failed)}`}
              />
            </div>

            {detail.last_error ? (
              <div>
                <div className="mb-1 text-xs font-semibold text-muted-foreground">
                  {t('exec.detail.lastError')}
                </div>
                <pre className="max-h-40 overflow-auto whitespace-pre-wrap break-words bg-muted p-3 text-xs" dir="ltr">
                  {detail.last_error}
                </pre>
              </div>
            ) : null}

            {detail.warnings.length > 0 ? (
              <div>
                <div className="mb-1 text-xs font-semibold text-muted-foreground">
                  {t('exec.detail.warnings')}
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {detail.warnings.map((w) => (
                    <span key={w} className="bg-amber-500/10 px-1.5 py-0.5 text-xs text-amber-600">
                      {w}
                    </span>
                  ))}
                </div>
              </div>
            ) : null}

            <div>
              <div className="mb-1 text-xs font-semibold text-muted-foreground">
                {t('exec.detail.checkpoints')}
              </div>
              <div className="grid grid-cols-2 gap-2 text-xs" dir="ltr">
                <CheckTime label="content" at={detail.checkpoints.content_imported_at} />
                <CheckTime label="media" at={detail.checkpoints.media_imported_at} />
                <CheckTime label="seo" at={detail.checkpoints.seo_imported_at} />
                <CheckTime label="redirects" at={detail.checkpoints.redirects_created_at} />
              </div>
            </div>
          </dl>
        ) : null}
      </Modal>
    </div>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="font-medium">{value}</dd>
    </div>
  );
}

function CheckTime({ label, at }: { label: string; at: string | null }) {
  return (
    <div className="flex items-center justify-between border border-border px-2 py-1">
      <span className="text-muted-foreground">{label}</span>
      <span className={at ? 'text-emerald-600' : 'text-muted-foreground'}>
        {at ? new Date(at).toLocaleString() : '—'}
      </span>
    </div>
  );
}
