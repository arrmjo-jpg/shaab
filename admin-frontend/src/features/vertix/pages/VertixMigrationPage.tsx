import { useTranslation } from 'react-i18next';
import {
  Database,
  FolderTree,
  Newspaper,
  Play,
  Square,
  CheckCircle2,
  AlertTriangle,
  Loader2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import {
  useVertixStatus,
  useImportVertixCategories,
  useImportVertixNews,
  useStopVertixNews,
} from '../hooks';
import type { VertixPhaseState, VertixPhaseStatus } from '@/services/vertix.service';

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n);

const STATUS_TONE: Record<VertixPhaseState, string> = {
  idle: 'bg-muted text-muted-foreground',
  running: 'bg-blue-500/10 text-blue-600',
  completed: 'bg-emerald-500/10 text-emerald-600',
  failed: 'bg-destructive/10 text-destructive',
};

function pct(news?: VertixPhaseStatus): number {
  if (!news) return 0;
  const total = news.imported + news.remaining;
  return total > 0 ? Math.min(100, Math.round((news.imported / total) * 100)) : 0;
}

function Metric({ label, value, tone }: { label: string; value: string; tone?: string }) {
  return (
    <div className={`border border-border bg-background p-4 ${tone ?? ''}`}>
      <div className="text-2xl font-bold tabular-nums">{value}</div>
      <div className="mt-1 text-xs text-muted-foreground">{label}</div>
    </div>
  );
}

export default function VertixMigrationPage() {
  const { t } = useTranslation('vertix');
  const { success } = useToast();
  const statusQ = useVertixStatus();
  const importCats = useImportVertixCategories();
  const importNews = useImportVertixNews();
  const stopNews = useStopVertixNews();

  const data = statusQ.data;
  const connected = data?.connected ?? false;
  const cats = data?.categories;
  const news = data?.news;
  const catsDone = (cats?.imported ?? 0) > 0;
  const newsRunning = news?.status === 'running';

  const badge = (s?: VertixPhaseState) =>
    s ? <span className={`px-2.5 py-1 text-xs font-bold ${STATUS_TONE[s]}`}>{t(`statusLabel.${s}`)}</span> : null;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold">{t('title')}</h1>
          <p className="text-sm text-muted-foreground">{t('subtitle')}</p>
        </div>
        <span
          className={`inline-flex items-center gap-2 px-2.5 py-1 text-xs font-bold ${
            connected ? 'bg-emerald-500/10 text-emerald-600' : 'bg-destructive/10 text-destructive'
          }`}
        >
          <Database className="h-4 w-4" />
          {connected ? t('connected') : t('disconnected')}
        </span>
      </div>

      {/* ── المرحلة 1: الأقسام ── */}
      <section className="border border-border bg-background p-5">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <FolderTree className="h-4 w-4 text-primary" />
            <h2 className="text-sm font-bold">{t('phase1')}</h2>
            {badge(cats?.status)}
          </div>
          <Button
            onClick={() => importCats.mutate(undefined, { onSuccess: () => success(t('importCategories')) })}
            disabled={!connected || importCats.isPending || cats?.status === 'running'}
          >
            {importCats.isPending ? (
              <Loader2 className="h-4 w-4 animate-spin" />
            ) : catsDone ? (
              <CheckCircle2 className="h-4 w-4" />
            ) : (
              <Play className="h-4 w-4" />
            )}
            {catsDone ? t('reimport') : t('importCategories')}
          </Button>
        </div>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Metric label={t('sourceTotal')} value={fmt(cats?.source_total ?? 0)} />
          <Metric label={t('imported')} value={fmt(cats?.imported ?? 0)} tone="border-emerald-500/40 bg-emerald-500/5" />
          <Metric label={t('remaining')} value={fmt(cats?.remaining ?? 0)} />
          <Metric
            label={t('failed')}
            value={fmt(cats?.failed ?? 0)}
            tone={(cats?.failed ?? 0) > 0 ? 'border-destructive/40 bg-destructive/5' : undefined}
          />
        </div>
      </section>

      {/* ── المرحلة 2: الأخبار ── */}
      <section className={`border border-border bg-background p-5 ${!catsDone ? 'opacity-60' : ''}`}>
        <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Newspaper className="h-4 w-4 text-primary" />
            <h2 className="text-sm font-bold">{t('phase2')}</h2>
            {badge(news?.status)}
          </div>
          {newsRunning ? (
            <Button
              variant="destructive"
              onClick={() => stopNews.mutate(undefined, { onSuccess: () => success(t('stop')) })}
              disabled={stopNews.isPending}
            >
              <Square className="h-4 w-4" />
              {t('stop')}
            </Button>
          ) : (
            <Button
              onClick={() => importNews.mutate(undefined, { onSuccess: () => success(t('importNews')) })}
              disabled={!connected || !catsDone || importNews.isPending}
            >
              {importNews.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Play className="h-4 w-4" />}
              {t('importNews')}
            </Button>
          )}
        </div>

        {catsDone ? (
          <p className="mb-3 text-xs text-muted-foreground">{t('newsHint')}</p>
        ) : (
          <p className="mb-3 text-xs text-amber-600">{t('phase2Locked')}</p>
        )}

        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Metric label={t('sourceTotal')} value={fmt(news?.source_total ?? 0)} />
          <Metric label={t('imported')} value={fmt(news?.imported ?? 0)} tone="border-emerald-500/40 bg-emerald-500/5" />
          <Metric label={t('remaining')} value={fmt(news?.remaining ?? 0)} />
          <Metric
            label={t('failed')}
            value={fmt(news?.failed ?? 0)}
            tone={(news?.failed ?? 0) > 0 ? 'border-destructive/40 bg-destructive/5' : undefined}
          />
        </div>

        {newsRunning ? (
          <div className="mt-3">
            <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
              <span>{t('running')}</span>
              <span className="tabular-nums">
                {fmt(news?.imported ?? 0)} / {fmt((news?.imported ?? 0) + (news?.remaining ?? 0))}
              </span>
            </div>
            <div className="h-2 w-full overflow-hidden bg-muted">
              <div className="h-full bg-primary transition-all" style={{ width: `${pct(news)}%` }} />
            </div>
          </div>
        ) : null}

        <p className="mt-3 text-[11px] text-muted-foreground" dir="ltr">
          {t('workerHint')}
        </p>
      </section>

      {/* ── سجل الأخطاء ── */}
      <section className="border border-border bg-background p-5">
        <div className="mb-3 flex items-center gap-2">
          <AlertTriangle className="h-4 w-4 text-primary" />
          <h2 className="text-sm font-bold">{t('errorsTitle')}</h2>
        </div>
        {data && data.errors.length > 0 ? (
          <div className="overflow-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-muted-foreground">
                <tr className="border-b border-border">
                  <th className="p-2 text-start font-medium">{t('errorSource')}</th>
                  <th className="p-2 text-start font-medium">{t('errorReason')}</th>
                </tr>
              </thead>
              <tbody>
                {data.errors.map((e, i) => (
                  <tr key={`${e.type}-${e.id}-${i}`} className="border-b border-border/60">
                    <td className="p-2 font-mono text-xs" dir="ltr">
                      {e.type} #{e.id}
                    </td>
                    <td className="p-2 text-xs text-destructive">{e.error}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">{t('errorsEmpty')}</p>
        )}
      </section>
    </div>
  );
}
