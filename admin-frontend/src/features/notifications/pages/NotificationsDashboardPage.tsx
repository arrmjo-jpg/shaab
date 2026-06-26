import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ErrorState, LoadingState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { paths } from '@/router/paths';
import { useCampaignSummary, useHealth } from '../hooks';
import { HEALTH_TONE, STATUS_TONE } from '../constants';
import type { CampaignStatus } from '@/types/notifications.types';

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <div className="border border-border bg-background p-4">
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-1 text-2xl font-bold tabular-nums">{value}</p>
    </div>
  );
}

export default function NotificationsDashboardPage() {
  const { t } = useTranslation('notifications');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const summary = useCampaignSummary();
  const health = useHealth();

  if (summary.isLoading) return <LoadingState />;
  if (summary.isError) return <ErrorState message={t('common.error')} onRetry={() => void summary.refetch()} />;

  const totals = summary.data?.totals;
  const byStatus = summary.data?.by_status ?? {};

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('dashboard.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={() => navigate(paths.notifMatrix)}>
            {t('dashboard.openMatrix')}
          </Button>
          {hasPermission('notifications.send') ? (
            <Button onClick={() => navigate(paths.notifCampaignCompose)}>
              <Send className="h-4 w-4" />
              {t('dashboard.quickCompose')}
            </Button>
          ) : null}
        </div>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label={t('dashboard.totalSent')} value={totals?.sent ?? 0} />
        <StatCard label={t('dashboard.totalFailed')} value={totals?.failed ?? 0} />
        <StatCard label={t('dashboard.totalSkipped')} value={totals?.skipped ?? 0} />
        <StatCard label={t('dashboard.totalInvalid')} value={totals?.invalid ?? 0} />
      </div>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold">{t('dashboard.byStatus')}</h2>
        <div className="flex flex-wrap gap-2">
          {Object.entries(byStatus).map(([st, count]) => (
            <Badge key={st} variant={STATUS_TONE[st as CampaignStatus] ?? 'muted'}>
              {t(`status.${st}`)} · {count}
            </Badge>
          ))}
          {Object.keys(byStatus).length === 0 ? (
            <span className="text-sm text-muted-foreground">—</span>
          ) : null}
        </div>
      </section>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold">{t('dashboard.channelsHealth')}</h2>
        <div className="grid gap-3 sm:grid-cols-3">
          {(health.data ?? []).map((h) => (
            <div
              key={h.channel}
              className="flex items-center justify-between border border-border bg-background p-3"
            >
              <span className="font-medium">{t(`channel.${h.channel}`)}</span>
              <Badge variant={HEALTH_TONE[h.effective_state ?? 'unconfigured']}>
                {t(`healthState.${h.effective_state ?? 'unconfigured'}`)}
              </Badge>
            </div>
          ))}
        </div>
      </section>
    </div>
  );
}
