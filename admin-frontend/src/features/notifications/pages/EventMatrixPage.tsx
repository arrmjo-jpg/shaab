import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SwitchField } from '@/components/form/SwitchField';
import { ErrorState, LoadingState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useMatrix, useToggleEvent } from '../hooks';
import { EventChannelModal } from '../components/EventChannelModal';
import type { EventChannelRow } from '@/types/notifications.types';

export default function EventMatrixPage() {
  const { t } = useTranslation('notifications');
  const { hasPermission } = useAuth();
  const canManage = hasPermission('notifications.manage');
  const q = useMatrix();
  const toggle = useToggleEvent();
  const [editing, setEditing] = useState<{ eventKey: string; row: EventChannelRow } | null>(null);

  if (q.isLoading) return <LoadingState />;
  if (q.isError) return <ErrorState message={t('common.error')} onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header>
        <h1 className="text-2xl font-bold">{t('matrix.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('matrix.subtitle')}</p>
      </header>

      <div className="space-y-4">
        {(q.data ?? []).map((ev) => (
          <div key={ev.id} className="space-y-3 border border-border bg-background p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="font-semibold">{ev.label}</p>
                <p className="text-xs text-muted-foreground">{ev.key}</p>
              </div>
              <div className="w-60">
                <SwitchField
                  label={t('matrix.enabled')}
                  checked={ev.enabled}
                  onChange={() => {
                    if (canManage) toggle.mutate(ev.id);
                  }}
                  disabled={!canManage}
                />
              </div>
            </div>

            {ev.variables.length > 0 ? (
              <div className="flex flex-wrap gap-1.5">
                {ev.variables.map((v) => (
                  <code key={v} className="rounded bg-muted px-1.5 py-0.5 text-xs">{`{{${v}}}`}</code>
                ))}
              </div>
            ) : null}

            <div className="divide-y divide-border border-t border-border">
              {ev.channels.map((ch) => (
                <div key={ch.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm">
                  <span className="w-28 font-medium">{t(`channel.${ch.channel}`)}</span>
                  <Badge variant={ch.mode === 'disabled' ? 'muted' : 'default'}>{t(`mode.${ch.mode}`)}</Badge>
                  <span className="text-muted-foreground">
                    {t('matrix.priority')}: <span className="tabular-nums">{ch.channel_priority}</span>
                  </span>
                  <span className="text-muted-foreground">
                    {t('matrix.template')}: {ch.template_id ?? t('matrix.none')}
                  </span>
                  {canManage ? (
                    <Button
                      variant="ghost"
                      size="icon"
                      className="ms-auto h-8 w-8"
                      onClick={() => setEditing({ eventKey: ev.key, row: ch })}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                  ) : null}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>

      {editing ? (
        <EventChannelModal
          open
          onClose={() => setEditing(null)}
          eventKey={editing.eventKey}
          row={editing.row}
        />
      ) : null}
    </div>
  );
}
