import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useDeleteTemplate, useTemplates } from '../hooks';
import { TemplateFormModal } from '../components/TemplateFormModal';
import type { TemplateData } from '@/types/notifications.types';

export default function TemplatesPage() {
  const { t } = useTranslation('notifications');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();
  const canManage = hasPermission('notifications.manage');
  const q = useTemplates();
  const del = useDeleteTemplate();
  const [editing, setEditing] = useState<TemplateData | null>(null);
  const [creating, setCreating] = useState(false);

  const onDelete = async (tpl: TemplateData) => {
    if (
      await confirm({
        title: t('templates.confirmDeleteTitle'),
        text: t('templates.confirmDeleteText'),
        confirmText: t('common.delete'),
        cancelText: t('common.cancel'),
      })
    )
      del.mutate(tpl.id);
  };

  const columns: Column<TemplateData>[] = [
    {
      key: 'event',
      header: t('templates.col.event'),
      render: (tp) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{tp.event_label}</p>
          <p className="truncate text-xs text-muted-foreground">{tp.title ?? '—'}</p>
        </div>
      ),
    },
    { key: 'channel', header: t('templates.col.channel'), render: (tp) => t(`channel.${tp.channel}`) },
    {
      key: 'locale',
      header: t('templates.col.locale'),
      align: 'center',
      render: (tp) => <span className="text-muted-foreground">{tp.locale ?? '—'}</span>,
    },
    {
      key: 'default',
      header: t('templates.col.default'),
      align: 'center',
      render: (tp) =>
        tp.is_default ? <Badge variant="success">✓</Badge> : <span className="text-muted-foreground">—</span>,
    },
  ];
  if (canManage) {
    columns.push({
      key: 'actions',
      header: '',
      align: 'end',
      render: (tp) => (
        <div className="flex justify-end gap-1">
          <Button variant="ghost" size="icon" className="h-8 w-8" onClick={() => setEditing(tp)}>
            <Pencil className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-destructive"
            onClick={() => void onDelete(tp)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    });
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('templates.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('templates.subtitle')}</p>
        </div>
        {canManage ? (
          <Button onClick={() => setCreating(true)}>
            <Plus className="h-4 w-4" />
            {t('templates.new')}
          </Button>
        ) : null}
      </header>

      {q.isError ? (
        <ErrorState message={t('common.error')} onRetry={() => void q.refetch()} />
      ) : (
        <DataTable
          columns={columns}
          rows={q.data ?? []}
          rowKey={(tp) => tp.id}
          loading={q.isLoading}
          emptyTitle={t('templates.empty.title')}
          emptyDescription={t('templates.empty.description')}
        />
      )}

      {creating || editing ? (
        <TemplateFormModal
          open
          onClose={() => {
            setCreating(false);
            setEditing(null);
          }}
          template={editing}
        />
      ) : null}
    </div>
  );
}
