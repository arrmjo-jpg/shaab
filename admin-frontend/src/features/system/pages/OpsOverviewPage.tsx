import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  CloudOff,
  Cloud,
  ListChecks,
  RefreshCw,
  XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ErrorState, PageSkeleton } from '@/components/feedback';
import { useOpsOverview } from '../hooks';

function StatCard({
  label,
  value,
  icon,
  tone = 'default',
}: {
  label: string;
  value: ReactNode;
  icon: ReactNode;
  tone?: 'default' | 'warn' | 'danger' | 'ok';
}) {
  const toneClass =
    tone === 'danger'
      ? 'text-destructive'
      : tone === 'warn'
        ? 'text-amber-600 dark:text-amber-400'
        : tone === 'ok'
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-foreground';

  return (
    <div className="flex items-center gap-4 rounded-2xl border border-border bg-background p-5 shadow-soft">
      <div className={`shrink-0 ${toneClass}`}>{icon}</div>
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className={`text-2xl font-bold ${toneClass}`}>{value}</p>
      </div>
    </div>
  );
}

export default function OpsOverviewPage() {
  const { t, i18n } = useTranslation('system');
  const q = useOpsOverview();

  const fmt = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(
          new Date(v),
        )
      : '—';

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const d = q.data;
  const num = (n: number, danger = false, warn = false) =>
    n > 0 && danger ? 'danger' : n > 0 && warn ? 'warn' : 'default';

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t('ops.title')}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{t('ops.subtitle')}</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
          <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          {t('ops.refresh')}
        </Button>
      </header>

      {/* الطابور */}
      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-muted-foreground">{t('ops.section.queue')}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label={t('ops.queue.pending')}
            value={d.queue.pending}
            icon={<ListChecks className="h-7 w-7" />}
          />
          <StatCard
            label={t('ops.queue.failed')}
            value={d.queue.failed}
            icon={<XCircle className="h-7 w-7" />}
            tone={num(d.queue.failed, true)}
          />
        </div>
      </section>

      {/* الوسائط */}
      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-muted-foreground">{t('ops.section.media')}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatCard
            label={t('ops.media.unsynced')}
            value={d.media.unsynced}
            icon={<Activity className="h-7 w-7" />}
            tone={num(d.media.unsynced, false, true)}
          />
          <StatCard
            label={t('ops.media.stuckTranscoding')}
            value={d.media.stuck_transcoding}
            icon={<AlertTriangle className="h-7 w-7" />}
            tone={num(d.media.stuck_transcoding, true)}
          />
          <StatCard
            label={t('ops.media.failedTranscode')}
            value={d.media.failed_transcode_24h}
            icon={<XCircle className="h-7 w-7" />}
            tone={num(d.media.failed_transcode_24h, false, true)}
          />
          <StatCard
            label={t('ops.media.failedMirror')}
            value={d.media.failed_mirror}
            icon={<CloudOff className="h-7 w-7" />}
            tone={num(d.media.failed_mirror, false, true)}
          />
        </div>
      </section>

      {/* المرآة + المُجدوِل */}
      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-muted-foreground">{t('ops.section.infra')}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label={t('ops.remote.title')}
            value={
              d.remote_healthy === null ? (
                <Badge variant="muted">{t('ops.remote.disabled')}</Badge>
              ) : d.remote_healthy ? (
                <Badge variant="success">{t('ops.remote.healthy')}</Badge>
              ) : (
                <Badge variant="destructive">{t('ops.remote.down')}</Badge>
              )
            }
            icon={
              d.remote_healthy === false ? (
                <CloudOff className="h-7 w-7" />
              ) : (
                <Cloud className="h-7 w-7" />
              )
            }
            tone={d.remote_healthy === false ? 'danger' : 'default'}
          />
          <StatCard
            label={t('ops.scheduler.failedLastRun')}
            value={d.scheduler.failed_last_run}
            icon={
              d.scheduler.failed_last_run > 0 ? (
                <AlertTriangle className="h-7 w-7" />
              ) : (
                <CheckCircle2 className="h-7 w-7" />
              )
            }
            tone={num(d.scheduler.failed_last_run, true)}
          />
          <StatCard
            label={t('ops.scheduler.lastRun')}
            value={<span className="text-sm font-medium">{fmt(d.scheduler.last_run_at)}</span>}
            icon={<Activity className="h-7 w-7" />}
          />
        </div>
      </section>
    </div>
  );
}
