import { useParams, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Check, Pause, Play, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState, LoadingState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { useCampaign, useCampaignLifecycle } from '../hooks';
import { STATUS_TONE } from '../constants';
import type { CampaignAction, CampaignChannel } from '@/types/notifications.types';

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' });
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="border border-border bg-background p-3">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-sm font-medium">{value}</p>
    </div>
  );
}

export default function CampaignDetailPage() {
  const { uuid } = useParams<{ uuid: string }>();
  const { t, i18n } = useTranslation('notifications');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm, success } = useToast();
  const q = useCampaign(uuid ?? null);
  const lifecycle = useCampaignLifecycle();
  const canManage = hasPermission('notifications.manage');

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState message={t('common.error')} onRetry={() => void q.refetch()} />;

  const c = q.data;

  const runAction = async (action: CampaignAction, confirmKey: string) => {
    if (
      await confirm({
        title: t(confirmKey),
        confirmText: t(`details.${action}`),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    ) {
      try {
        await lifecycle.mutateAsync({ uuid: c.uuid, action });
        success(t('details.actionDone'));
      } catch {
        /* خطأ يُعرض عبر الخطّاف */
      }
    }
  };

  const showApprove = canManage && c.status === 'draft';
  const showPause = canManage && c.allowed_transitions.includes('paused');
  const showResume = canManage && c.status === 'paused';
  const showCancel = canManage && c.allowed_transitions.includes('cancelled');

  const channelCols: Column<CampaignChannel>[] = [
    { key: 'channel', header: t('details.col.channel'), render: (ch) => t(`channel.${ch.channel}`) },
    {
      key: 'status',
      header: t('details.col.status'),
      render: (ch) => <span className="text-sm text-muted-foreground">{ch.status}</span>,
    },
    { key: 'targeted', header: t('details.col.targeted'), align: 'center', render: (ch) => <span className="tabular-nums">{ch.counters.targeted}</span> },
    { key: 'sent', header: t('details.col.sent'), align: 'center', render: (ch) => <span className="tabular-nums">{ch.counters.sent}</span> },
    { key: 'failed', header: t('details.col.failed'), align: 'center', render: (ch) => <span className="tabular-nums">{ch.counters.failed}</span> },
    { key: 'skipped', header: t('details.col.skipped'), align: 'center', render: (ch) => <span className="tabular-nums">{ch.counters.skipped}</span> },
    { key: 'invalid', header: t('details.col.invalid'), align: 'center', render: (ch) => <span className="tabular-nums">{ch.counters.invalid}</span> },
  ];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="icon" onClick={() => navigate(paths.notifCampaigns)}>
            <ArrowRight className="h-4 w-4 rtl:rotate-0 ltr:rotate-180" />
          </Button>
          <div>
            <h1 className="text-xl font-bold">{c.title ?? c.event_label}</h1>
            <div className="mt-1 flex items-center gap-2">
              <Badge variant={STATUS_TONE[c.status]}>{c.status_label}</Badge>
              <span className="text-xs text-muted-foreground">{c.event_label}</span>
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {showApprove ? (
            <Button onClick={() => void runAction('approve', 'details.confirmApprove')} disabled={lifecycle.isPending}>
              <Check className="h-4 w-4" />
              {t('details.approve')}
            </Button>
          ) : null}
          {showResume ? (
            <Button onClick={() => void runAction('resume', 'details.confirmResume')} disabled={lifecycle.isPending}>
              <Play className="h-4 w-4" />
              {t('details.resume')}
            </Button>
          ) : null}
          {showPause ? (
            <Button variant="outline" onClick={() => void runAction('pause', 'details.confirmPause')} disabled={lifecycle.isPending}>
              <Pause className="h-4 w-4" />
              {t('details.pause')}
            </Button>
          ) : null}
          {showCancel ? (
            <Button variant="destructive" onClick={() => void runAction('cancel', 'details.confirmCancel')} disabled={lifecycle.isPending}>
              <X className="h-4 w-4" />
              {t('details.cancel')}
            </Button>
          ) : null}
        </div>
      </header>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Info label={t('details.scheduledAt')} value={fmtDate(c.scheduled_at, i18n.language)} />
        <Info label={t('details.startedAt')} value={fmtDate(c.started_at, i18n.language)} />
        <Info label={t('details.finishedAt')} value={fmtDate(c.finished_at, i18n.language)} />
        <Info label={t('details.stats')} value={`${c.stats.sent} / ${c.stats.targeted}`} />
      </div>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold">{t('details.channelsTitle')}</h2>
        <DataTable columns={channelCols} rows={c.channels ?? []} rowKey={(ch) => ch.id} />
      </section>
    </div>
  );
}
