import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Play, Pencil, Loader2, AlertTriangle } from 'lucide-react';
import { Badge, type BadgeProps } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { SwitchField } from '@/components/form/SwitchField';
import { TextareaField } from '@/components/form/TextareaField';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/hooks/useAuth';
import { useScheduledTasks, useUpdateScheduledTask, useRunScheduledTask } from '../hooks';
import type { ScheduledTask, TaskHealth, LastStatus } from '@/types/scheduler.types';

const HEALTH_VARIANT: Record<TaskHealth, BadgeProps['variant']> = {
  healthy: 'success',
  stale: 'default',
  failed: 'destructive',
  never: 'muted',
  disabled: 'muted',
};

const STATUS_VARIANT: Record<LastStatus, BadgeProps['variant']> = {
  success: 'success',
  failed: 'destructive',
  running: 'default',
  never: 'muted',
};

export default function SchedulerPage() {
  const { t, i18n } = useTranslation('system');
  const { hasPermission } = useAuth();
  const canManage = hasPermission('scheduler.manage');
  const canRun = hasPermission('scheduler.run');

  const q = useScheduledTasks();
  const update = useUpdateScheduledTask();
  const run = useRunScheduledTask();
  const { success, error, confirm } = useToast();

  const [editing, setEditing] = useState<ScheduledTask | null>(null);
  const [enabled, setEnabled] = useState(true);
  const [notes, setNotes] = useState('');
  const [running, setRunning] = useState<string | null>(null);

  const fmt = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, {
          dateStyle: 'medium',
          timeStyle: 'short',
        }).format(new Date(v))
      : '—';

  const openEdit = (task: ScheduledTask) => {
    setEditing(task);
    setEnabled(task.enabled);
    setNotes(task.notes ?? '');
  };

  const saveEdit = () => {
    if (!editing) return;
    update.mutate(
      { key: editing.key, payload: { enabled, notes: notes.trim() || null } },
      {
        onSuccess: () => {
          success(t('scheduler.toast.saved'));
          setEditing(null);
        },
      },
    );
  };

  const onRun = async (task: ScheduledTask) => {
    const ok = await confirm({
      title: t('scheduler.confirm.runTitle'),
      text: t('scheduler.confirm.runText', { name: task.name }),
      confirmText: t('scheduler.confirm.runYes'),
      cancelText: t('scheduler.confirm.cancel'),
    });
    if (!ok) return;
    setRunning(task.key);
    try {
      const result = await run.mutateAsync(task.key);
      if (result.last_status === 'failed') {
        error(result.last_error ?? t('scheduler.toast.runFailed'));
      } else {
        success(t('scheduler.toast.runOk'));
      }
    } catch {
      /* HTTP errors (403/429) handled by hook toast */
    } finally {
      setRunning(null);
    }
  };

  const columns: Column<ScheduledTask>[] = [
    {
      key: 'task',
      header: t('scheduler.col.task'),
      render: (r) => (
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <p className="font-medium">{r.name}</p>
            {r.critical ? (
              <Badge variant="destructive">{t('scheduler.critical')}</Badge>
            ) : null}
          </div>
          <p className="text-xs text-muted-foreground">{r.description}</p>
          {r.notes ? (
            <p className="mt-1 text-xs text-muted-foreground/80">“{r.notes}”</p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'schedule',
      header: t('scheduler.col.schedule'),
      render: (r) => (
        <div className="space-y-0.5">
          <p className="text-sm">{r.frequency}</p>
          <code className="text-xs text-muted-foreground" dir="ltr">
            {r.cron ?? '—'}
          </code>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('scheduler.col.status'),
      render: (r) => (
        <div className="flex flex-col items-start gap-1">
          <Badge variant={HEALTH_VARIANT[r.health]}>
            {t(`scheduler.health.${r.health}`)}
          </Badge>
          {!r.enabled ? (
            <span className="text-xs text-muted-foreground">
              {t('scheduler.disabledHint')}
            </span>
          ) : null}
        </div>
      ),
    },
    {
      key: 'lastRun',
      header: t('scheduler.col.lastRun'),
      render: (r) => (
        <div className="space-y-0.5">
          <p className="whitespace-nowrap text-sm text-muted-foreground">
            {fmt(r.last_run_at)}
          </p>
          <div className="flex items-center gap-1.5">
            <Badge variant={STATUS_VARIANT[r.last_status]}>
              {t(`scheduler.status.${r.last_status}`)}
            </Badge>
            {r.last_runtime_ms != null ? (
              <span className="text-xs text-muted-foreground" dir="ltr">
                {r.last_runtime_ms} ms
              </span>
            ) : null}
          </div>
          {r.last_status === 'failed' && r.last_error ? (
            <p
              className="mt-1 flex items-start gap-1 text-xs text-destructive"
              title={r.last_error}
            >
              <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
              <span className="line-clamp-2">{r.last_error}</span>
            </p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'nextRun',
      header: t('scheduler.col.nextRun'),
      render: (r) => (
        <span className="whitespace-nowrap text-sm text-muted-foreground">
          {fmt(r.next_run_at)}
        </span>
      ),
    },
    {
      key: 'actions',
      header: t('scheduler.col.actions'),
      align: 'end',
      render: (r) => (
        <div className="flex items-center justify-end gap-2">
          {canRun && r.manual_run_allowed ? (
            <Button
              variant="outline"
              size="sm"
              onClick={() => void onRun(r)}
              disabled={running === r.key}
            >
              {running === r.key ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Play className="h-4 w-4" />
              )}
              {t('scheduler.action.run')}
            </Button>
          ) : null}
          {canManage ? (
            <Button variant="ghost" size="sm" onClick={() => openEdit(r)}>
              <Pencil className="h-4 w-4" />
              {t('scheduler.action.manage')}
            </Button>
          ) : null}
        </div>
      ),
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('scheduler.title')}</h1>
        <p className="mt-1 text-sm text-muted-foreground">{t('scheduler.subtitle')}</p>
      </header>

      <div className="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
        {t('scheduler.notice')}
      </div>

      <DataTable
        columns={columns}
        rows={q.data ?? []}
        rowKey={(r) => r.key}
        loading={q.isLoading}
        emptyTitle={t('scheduler.empty')}
      />

      <Modal
        open={editing !== null}
        onClose={() => setEditing(null)}
        title={editing ? t('scheduler.modal.title', { name: editing.name }) : ''}
        description={t('scheduler.modal.desc')}
        size="md"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={() => setEditing(null)}>
              {t('scheduler.confirm.cancel')}
            </Button>
            <Button size="sm" onClick={saveEdit} disabled={update.isPending}>
              {update.isPending ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : null}
              {t('scheduler.modal.save')}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <SwitchField
            label={t('scheduler.modal.enabled')}
            description={t('scheduler.modal.enabledHint')}
            checked={enabled}
            onChange={setEnabled}
          />
          <TextareaField
            label={t('scheduler.modal.notes')}
            name="notes"
            value={notes}
            maxLength={1000}
            placeholder={t('scheduler.modal.notesPlaceholder')}
            onChange={(e) => setNotes(e.target.value)}
          />
        </div>
      </Modal>
    </div>
  );
}
