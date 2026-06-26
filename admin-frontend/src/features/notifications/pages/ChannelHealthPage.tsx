import { useTranslation } from 'react-i18next';
import { RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState, ErrorState, LoadingState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useHealth, useProbeChannels } from '../hooks';
import { HEALTH_TONE } from '../constants';

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale, { dateStyle: 'short', timeStyle: 'short' });
}

export default function ChannelHealthPage() {
  const { t, i18n } = useTranslation('notifications');
  const { hasPermission } = useAuth();
  const { success } = useToast();
  const q = useHealth();
  const probe = useProbeChannels();
  const canProbe = hasPermission('notifications.manage');

  const onProbe = async () => {
    try {
      await probe.mutateAsync();
      success(t('health.probed'));
    } catch {
      /* خطأ يُعرض عبر الخطّاف */
    }
  };

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('health.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('health.subtitle')}</p>
        </div>
        {canProbe ? (
          <Button onClick={() => void onProbe()} disabled={probe.isPending}>
            <RefreshCw className={`h-4 w-4 ${probe.isPending ? 'animate-spin' : ''}`} />
            {probe.isPending ? t('health.probing') : t('health.probe')}
          </Button>
        ) : null}
      </header>

      {q.isLoading ? (
        <LoadingState />
      ) : q.isError ? (
        <ErrorState message={t('common.error')} onRetry={() => void q.refetch()} />
      ) : (q.data ?? []).length === 0 ? (
        <EmptyState title={t('health.empty.title')} description={t('health.empty.description')} />
      ) : (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {(q.data ?? []).map((h) => (
            <div key={h.channel} className="space-y-3 border border-border bg-background p-4">
              <div className="flex items-center justify-between">
                <span className="font-semibold">{t(`channel.${h.channel}`)}</span>
                <Badge variant={HEALTH_TONE[h.effective_state ?? 'unconfigured']}>
                  {t(`healthState.${h.effective_state ?? 'unconfigured'}`)}
                </Badge>
              </div>
              <dl className="space-y-1.5 text-sm">
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">{t('health.configured')}</dt>
                  <dd>{h.configured ? '✓' : '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">{t('health.sendable')}</dt>
                  <dd>{h.sendable ? '✓' : '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">{t('health.failures')}</dt>
                  <dd className="tabular-nums">{h.consecutive_failures ?? 0}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">{t('health.latency')}</dt>
                  <dd className="tabular-nums">{h.latency_ms != null ? `${h.latency_ms}ms` : '—'}</dd>
                </div>
                <div className="flex justify-between">
                  <dt className="text-muted-foreground">{t('health.lastChecked')}</dt>
                  <dd>{fmtDate(h.last_checked_at, i18n.language)}</dd>
                </div>
                {h.last_error ? (
                  <p className="border-t border-border pt-2 text-xs text-destructive">{h.last_error}</p>
                ) : null}
              </dl>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
