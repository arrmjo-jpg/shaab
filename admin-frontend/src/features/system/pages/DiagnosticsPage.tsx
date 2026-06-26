import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  CheckCircle2,
  Database,
  HardDriveDownload,
  RefreshCw,
  Server,
  Tag,
  Trash2,
  XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ErrorState, PageSkeleton } from '@/components/feedback';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/hooks/useAuth';
import { useClearContentCache, useDiagnostics } from '../hooks';

function Row({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-border py-2.5 last:border-0">
      <span className="text-sm text-muted-foreground">{label}</span>
      <span className="text-sm font-medium" dir="ltr">
        {value}
      </span>
    </div>
  );
}

function Card({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="rounded-2xl border border-border bg-background p-5 shadow-soft">
      <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{title}</h2>
      <div>{children}</div>
    </div>
  );
}

export default function DiagnosticsPage() {
  const { t, i18n } = useTranslation('system');
  const { hasPermission } = useAuth();
  const { success, confirm } = useToast();
  const canClear = hasPermission('cache.clear');

  const q = useDiagnostics();
  const clear = useClearContentCache();

  const okBadge = (ok: boolean) =>
    ok ? (
      <Badge variant="success">
        <CheckCircle2 className="h-3 w-3" />
        {t('diagnostics.status.ok')}
      </Badge>
    ) : (
      <Badge variant="destructive">
        <XCircle className="h-3 w-3" />
        {t('diagnostics.status.down')}
      </Badge>
    );

  const boolBadge = (v: boolean) =>
    v ? (
      <Badge variant="success">{t('diagnostics.status.yes')}</Badge>
    ) : (
      <Badge variant="muted">{t('diagnostics.status.no')}</Badge>
    );

  const fmtDate = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(
          new Date(v),
        )
      : '—';

  const runClear = async () => {
    if (!canClear) return;
    const confirmed = await confirm({
      title: t('cacheClear.confirmTitle'),
      text: t('cacheClear.confirmText'),
      confirmText: t('cacheClear.confirmYes'),
      cancelText: t('cacheClear.cancel'),
    });
    if (!confirmed) return;
    clear.mutate(undefined, {
      onSuccess: () => success(t('cacheClear.success')),
    });
  };

  if (q.isLoading && !q.data) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const d = q.data;

  return (
    <div className="space-y-6">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t('diagnostics.title')}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{t('diagnostics.subtitle')}</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
          <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          {t('diagnostics.refresh')}
        </Button>
      </header>

      {/* وضع الصيانة */}
      <section
        className={`flex items-start gap-3 rounded-2xl border p-4 ${
          d.maintenance.down
            ? 'border-amber-500/40 bg-amber-500/10'
            : 'border-emerald-500/30 bg-emerald-500/5'
        }`}
      >
        {d.maintenance.down ? (
          <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
        ) : (
          <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
        )}
        <div className="min-w-0">
          <p className="text-sm font-semibold">
            {d.maintenance.down ? t('diagnostics.maintenance.down') : t('diagnostics.maintenance.up')}
          </p>
          <p className="mt-0.5 text-xs text-muted-foreground">{t('diagnostics.maintenance.hint')}</p>
          {d.app.debug ? (
            <p className="mt-1 flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
              <AlertTriangle className="h-3.5 w-3.5" />
              {t('diagnostics.maintenance.debugWarn')}
            </p>
          ) : null}
        </div>
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* بيئة التشغيل */}
        <Card title={t('diagnostics.section.runtime')}>
          <Row label={t('diagnostics.runtime.environment')} value={d.app.environment} />
          <Row label={t('diagnostics.runtime.laravel')} value={d.app.laravel_version} />
          <Row label={t('diagnostics.runtime.php')} value={d.app.php_version} />
          <Row label={t('diagnostics.runtime.debug')} value={boolBadge(d.app.debug)} />
          <Row label={t('diagnostics.runtime.locale')} value={d.app.locale} />
          <Row label={t('diagnostics.runtime.timezone')} value={d.app.timezone} />
          <Row label={t('diagnostics.runtime.url')} value={d.app.url} />
          <Row label={t('diagnostics.runtime.opcache')} value={boolBadge(d.opcache)} />
        </Card>

        {/* المشغّلات */}
        <Card title={t('diagnostics.drivers.title')}>
          <Row label={t('diagnostics.drivers.cache')} value={d.drivers.cache} />
          <Row label={t('diagnostics.drivers.queue')} value={d.drivers.queue} />
          <Row label={t('diagnostics.drivers.session')} value={d.drivers.session} />
          <Row label={t('diagnostics.drivers.database')} value={d.drivers.database} />
          <Row label={t('diagnostics.drivers.mail')} value={d.drivers.mail} />
        </Card>

        {/* الاتصال والصحّة */}
        <Card title={t('diagnostics.section.connectivity')}>
          <Row
            label={t('diagnostics.connectivity.database')}
            value={
              <span className="inline-flex items-center gap-2">
                <Database className="h-4 w-4 text-muted-foreground" />
                {okBadge(d.connectivity.database)}
              </span>
            }
          />
          <Row
            label={t('diagnostics.connectivity.cache')}
            value={
              <span className="inline-flex items-center gap-2">
                <Server className="h-4 w-4 text-muted-foreground" />
                {okBadge(d.connectivity.cache)}
              </span>
            }
          />
          <Row
            label={t('diagnostics.connectivity.cacheTagging')}
            value={
              <span className="inline-flex items-center gap-2">
                <Tag className="h-4 w-4 text-muted-foreground" />
                {boolBadge(d.cache.supports_tagging)}
              </span>
            }
          />
        </Card>

        {/* الطابور والمُجدوِل */}
        <Card title={t('diagnostics.section.queueScheduler')}>
          <Row label={t('diagnostics.queue.pending')} value={d.queue.pending} />
          <Row
            label={t('diagnostics.queue.failed')}
            value={
              d.queue.failed > 0 ? (
                <span className="font-semibold text-destructive">{d.queue.failed}</span>
              ) : (
                d.queue.failed
              )
            }
          />
          <Row label={t('diagnostics.scheduler.tasks')} value={d.scheduler.tasks} />
          <Row label={t('diagnostics.scheduler.lastRun')} value={fmtDate(d.scheduler.last_run_at)} />
        </Card>
      </div>

      {/* تفريغ كاش المحتوى */}
      <section className="rounded-2xl border border-border bg-background p-5 shadow-soft">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <h2 className="flex items-center gap-2 text-sm font-semibold">
              <HardDriveDownload className="h-4 w-4 text-primary" />
              {t('cacheClear.title')}
            </h2>
            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{t('cacheClear.desc')}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t('cacheClear.auditNote')}</p>
            {clear.isSuccess && clear.data ? (
              <p className="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                {t('cacheClear.clearedGroups', { groups: clear.data.cleared.join('، ') })}
              </p>
            ) : null}
            {!canClear ? (
              <p className="mt-2 text-xs text-muted-foreground">{t('cacheClear.noPermission')}</p>
            ) : null}
          </div>
          {canClear ? (
            <Button
              variant="destructive"
              size="sm"
              disabled={clear.isPending}
              onClick={() => void runClear()}
              className="shrink-0"
            >
              <Trash2 className={clear.isPending ? 'h-4 w-4 animate-pulse' : 'h-4 w-4'} />
              {t('cacheClear.button')}
            </Button>
          ) : null}
        </div>
      </section>
    </div>
  );
}
