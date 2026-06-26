import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ShieldAlert } from 'lucide-react';
import { Panel } from '@/components/analytics/AnalyticsKit';
import { useAuth } from '@/stores/auth.store';
import { paths } from '@/router/paths';
import { usePendingCommentsCount, usePendingWriterRequestsCount } from '../dashboard.hooks';

function ModRow({
  label,
  to,
  value,
  isLoading,
  isError,
}: {
  label: string;
  to: string;
  value: number | undefined;
  isLoading: boolean;
  isError: boolean;
}) {
  return (
    <Link
      to={to}
      className="flex items-center justify-between gap-3 border border-border bg-background p-3 text-sm transition-colors hover:bg-muted"
    >
      <span className="text-muted-foreground">{label}</span>
      <span className="font-bold tabular-nums">{isError ? '—' : isLoading ? '…' : (value ?? 0)}</span>
    </Link>
  );
}

/** عدّادا الإشراف المعلّق — من pagination.total لقوائم قائمة (status=pending). */
export default function PendingModeration() {
  const { t } = useTranslation('common');
  const { hasPermission } = useAuth();
  const canComments = hasPermission('comments.view');
  const canWriters = hasPermission('writer-requests.review');

  const comments = usePendingCommentsCount(canComments);
  const writers = usePendingWriterRequestsCount(canWriters);

  if (!canComments && !canWriters) return null;

  return (
    <Panel title={t('dashboard.moderation.title')} icon={ShieldAlert}>
      <div className="space-y-2">
        {canComments ? (
          <ModRow
            label={t('dashboard.moderation.comments')}
            to={paths.comments}
            value={comments.data}
            isLoading={comments.isLoading}
            isError={comments.isError}
          />
        ) : null}
        {canWriters ? (
          <ModRow
            label={t('dashboard.moderation.writerRequests')}
            to={paths.writerRequests}
            value={writers.data}
            isLoading={writers.isLoading}
            isError={writers.isError}
          />
        ) : null}
      </div>
    </Panel>
  );
}
