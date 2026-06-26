import { useTranslation } from 'react-i18next';
import {
  AlertTriangle,
  CalendarClock,
  CheckCircle2,
  Clock,
  Loader2,
  RefreshCw,
  RotateCw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useReprocessVideo, useVideoOperations } from '../hooks';
import { MetricCard, Panel } from '../components/StatPrimitives';
import type { VideoOperationsAttentionItem } from '@/types/videoLibrary.types';

function fmt(n: number, locale: string): string {
  return n.toLocaleString(locale);
}

function fmtDateTime(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(locale, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZoneName: 'short',
  });
}

/**
 * مركز عمليات مكتبة الفيديو — أدوات صيانة حقيقية فقط: صحّة المعالجة، قائمة
 * الفيديوهات المحتاجة انتباهاً مع إعادة معالجة (بتأكيد)، وصحّة طابور النشر.
 * لا عناصر تجميلية ولا أزرار بلا وظيفة.
 */
export default function OperationsPage() {
  const { t, i18n } = useTranslation('videoLibrary');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();
  const ops = useVideoOperations();
  const reprocess = useReprocessVideo();
  const locale = i18n.language;
  const canReprocess = hasPermission('videos.reprocess');

  const isReprocessing = (id: number) => reprocess.isPending && reprocess.variables === id;

  const onReprocess = async (item: VideoOperationsAttentionItem) => {
    const ok = await confirm({
      title: t('operations.attention.confirmTitle'),
      text: t('operations.attention.confirmText', { title: item.title }),
      confirmText: t('operations.attention.confirmYes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (ok) reprocess.mutate(item.id);
  };

  const Header = (
    <header className="flex items-start justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold">{t('operations.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('operations.subtitle')}</p>
      </div>
      <Button
        variant="outline"
        size="sm"
        onClick={() => void ops.refetch()}
        disabled={ops.isFetching}
      >
        <RefreshCw className={cn('h-4 w-4', ops.isFetching && 'animate-spin')} />
        {t('operations.refresh')}
      </Button>
    </header>
  );

  if (ops.isError) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('operations.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void ops.refetch()}>
            {t('operations.retry')}
          </Button>
        </div>
      </div>
    );
  }

  if (ops.isLoading || !ops.data) {
    return (
      <div className="space-y-6">
        {Header}
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-[72px] w-full" />
          ))}
        </div>
        <Skeleton className="h-64 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    );
  }

  const data = ops.data;
  const ph = data.processing_health;
  const pq = data.publish_queue;

  return (
    <div className="space-y-6">
      {Header}

      {/* Operational KPIs */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <MetricCard
          label={t('operations.kpi.processing')}
          value={fmt(ph.processing, locale)}
          icon={Loader2}
          tone="text-amber-600 dark:text-amber-400"
        />
        <MetricCard
          label={t('operations.kpi.failed')}
          value={fmt(ph.failed, locale)}
          icon={AlertTriangle}
          tone={ph.failed > 0 ? 'text-destructive' : 'text-muted-foreground'}
        />
        <MetricCard
          label={t('operations.kpi.scheduled')}
          value={fmt(pq.scheduled_total, locale)}
          icon={CalendarClock}
        />
        <MetricCard
          label={t('operations.kpi.dueNow')}
          value={fmt(pq.due_now, locale)}
          icon={Clock}
          tone={pq.due_now > 0 ? 'text-sky-600 dark:text-sky-400' : 'text-muted-foreground'}
        />
      </div>

      {/* Needs attention — failed/processing uploaded media with reprocess action */}
      <Panel
        title={t('operations.attention.title')}
        subtitle={t('operations.attention.subtitle')}
        icon={AlertTriangle}
      >
        {data.needs_attention.length === 0 ? (
          <div className="flex items-center gap-2 py-6 text-sm text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 className="h-4 w-4" />
            {t('operations.attention.empty')}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-xs text-muted-foreground">
                  <th className="py-2 pe-3 text-start font-medium">{t('operations.attention.col.title')}</th>
                  <th className="py-2 pe-3 text-start font-medium">{t('operations.attention.col.status')}</th>
                  <th className="py-2 pe-3 text-start font-medium">{t('operations.attention.col.updated')}</th>
                  <th className="py-2 text-end font-medium" />
                </tr>
              </thead>
              <tbody>
                {data.needs_attention.map((item) => (
                  <tr key={item.id} className="border-b border-border/60 last:border-0">
                    <td className="py-2 pe-3">
                      <span className="block max-w-[22rem] truncate font-medium">{item.title}</span>
                      <span className="text-xs text-muted-foreground">{t(`locale.${item.locale}`)}</span>
                    </td>
                    <td className="py-2 pe-3">
                      <Badge variant={item.processing_status === 'failed' ? 'destructive' : 'muted'}>
                        {t(`processing.${item.processing_status ?? 'none'}`)}
                      </Badge>
                    </td>
                    <td className="py-2 pe-3 text-xs tabular-nums text-muted-foreground">
                      {fmtDateTime(item.updated_at, locale)}
                    </td>
                    <td className="py-2 text-end">
                      {canReprocess ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => void onReprocess(item)}
                          disabled={isReprocessing(item.id)}
                        >
                          {isReprocessing(item.id) ? (
                            <>
                              <Loader2 className="h-3.5 w-3.5 animate-spin" />
                              {t('operations.attention.reprocessing')}
                            </>
                          ) : (
                            <>
                              <RotateCw className="h-3.5 w-3.5" />
                              {t('operations.attention.reprocess')}
                            </>
                          )}
                        </Button>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      {/* Publish queue health */}
      <Panel
        title={t('operations.queue.title')}
        subtitle={t('operations.queue.subtitle')}
        icon={CalendarClock}
      >
        {pq.upcoming.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">{t('operations.queue.empty')}</p>
        ) : (
          <ul className="space-y-2">
            {pq.upcoming.map((item) => (
              <li key={item.id} className="flex items-center gap-3 border border-border p-3 text-sm">
                <CalendarClock className="h-4 w-4 shrink-0 text-muted-foreground" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium">{item.title}</span>
                  <span className="text-xs text-muted-foreground">
                    {t('operations.queue.scheduledFor', { date: fmtDateTime(item.published_at, locale) })}
                  </span>
                </span>
                {item.overdue ? (
                  <Badge variant="destructive">{t('operations.queue.overdue')}</Badge>
                ) : (
                  <Badge variant="muted">{t(`locale.${item.locale}`)}</Badge>
                )}
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
