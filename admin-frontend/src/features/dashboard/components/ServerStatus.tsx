import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Server } from 'lucide-react';
import { Panel } from '@/components/analytics/AnalyticsKit';
import { Skeleton } from '@/components/ui/skeleton';
import { useAuth } from '@/stores/auth.store';
import { useDiagnostics, useOpsOverview } from '@/features/system/hooks';

function Dot({ ok }: { ok: boolean }) {
  return (
    <span
      className={ok ? 'inline-block h-2 w-2 bg-emerald-500' : 'inline-block h-2 w-2 bg-destructive'}
      aria-hidden
    />
  );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-3 py-1.5 text-sm">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="flex items-center gap-2 font-medium tabular-nums">{children}</dd>
    </div>
  );
}

function ServerStatusInner() {
  const { t } = useTranslation('common');
  const diag = useDiagnostics();
  const ops = useOpsOverview();

  return (
    <Panel title={t('dashboard.server.title')} icon={Server}>
      {diag.isError ? (
        <p className="text-sm text-destructive">{t('dashboard.error')}</p>
      ) : diag.isLoading || !diag.data ? (
        <div className="space-y-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-6 w-full" />
          ))}
        </div>
      ) : (
        <dl className="divide-y divide-border">
          <Row label={t('dashboard.server.php')}>{diag.data.app.php_version}</Row>
          <Row label={t('dashboard.server.laravel')}>{diag.data.app.laravel_version}</Row>
          <Row label={t('dashboard.server.environment')}>{diag.data.app.environment}</Row>
          <Row label={t('dashboard.server.cache')}>
            <Dot ok={diag.data.connectivity.cache} />
            <span>{diag.data.drivers.cache}</span>
          </Row>
          <Row label={t('dashboard.server.database')}>
            <Dot ok={diag.data.connectivity.database} />
            <span>{diag.data.drivers.database}</span>
          </Row>
          <Row label={t('dashboard.server.queuePending')}>{diag.data.queue.pending}</Row>
          <Row label={t('dashboard.server.queueFailed')}>{diag.data.queue.failed}</Row>
          <Row label={t('dashboard.server.scheduler')}>{diag.data.scheduler.tasks}</Row>
          <Row label={t('dashboard.server.lastRun')}>
            {diag.data.scheduler.last_run_at
              ? new Date(diag.data.scheduler.last_run_at).toLocaleString()
              : t('dashboard.server.never')}
          </Row>
          {ops.data ? (
            <>
              <Row label={t('dashboard.server.storage')}>
                {ops.data.remote_healthy === null ? (
                  <span className="text-muted-foreground">—</span>
                ) : (
                  <>
                    <Dot ok={ops.data.remote_healthy} />
                    <span>
                      {ops.data.remote_healthy
                        ? t('dashboard.server.healthy')
                        : t('dashboard.server.unhealthy')}
                    </span>
                  </>
                )}
              </Row>
              <Row label={t('dashboard.server.unsynced')}>{ops.data.media.unsynced}</Row>
              <Row label={t('dashboard.server.syncFailed')}>{ops.data.media.sync_failed}</Row>
            </>
          ) : null}
        </dl>
      )}
    </Panel>
  );
}

/** حالة الخادم — diagnostics + تخزين الوسائط (ops-overview). يتطلّب scheduler.view القائمة. */
export default function ServerStatus() {
  const { hasPermission } = useAuth();
  if (!hasPermission('scheduler.view')) return null;
  return <ServerStatusInner />;
}
