import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useDeleteWhatsappGroup, useWhatsappGroups } from '../hooks';
import { WhatsappGroupFormModal } from '../components/WhatsappGroupFormModal';
import type { WhatsappGroupData } from '@/types/whatsapp.types';

/** مجموعات واتساب — قائمة + إنشاء/تعديل بمودال + حذف (الافتراضية محمية). */
export default function WhatsappGroupsPage() {
  const { t } = useTranslation('whatsapp');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canManage = hasPermission('whatsapp.manage');

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<WhatsappGroupData | null>(null);

  const q = useWhatsappGroups();
  const del = useDeleteWhatsappGroup();

  const rows = q.data ?? [];

  const openCreate = () => {
    setEditing(null);
    setModalOpen(true);
  };
  const openEdit = (g: WhatsappGroupData) => {
    setEditing(g);
    setModalOpen(true);
  };
  const onDelete = async (g: WhatsappGroupData) => {
    if (
      await confirm({
        title: t('groups.confirm.deleteTitle'),
        text: t('groups.confirm.deleteText', { name: g.name }),
        confirmText: t('groups.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(g.id);
  };

  const columns: Column<WhatsappGroupData>[] = [
    {
      key: 'name',
      header: t('groups.col.name'),
      render: (g) => (
        <div className="min-w-0">
          <p className="truncate font-medium">
            {g.name}{' '}
            {g.is_default ? <Badge variant="success">{t('groups.defaultBadge')}</Badge> : null}
          </p>
          {g.description ? (
            <p className="truncate text-xs text-muted-foreground">{g.description}</p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'count',
      header: t('groups.col.count'),
      align: 'center',
      render: (g) => g.contacts_count,
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (g) =>
        canManage ? (
          <div className="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" aria-label={t('groups.edit')} onClick={() => openEdit(g)}>
              <Pencil className="h-4 w-4" />
            </Button>
            {!g.is_default ? (
              <Button variant="ghost" size="icon" aria-label={t('groups.delete')} onClick={() => void onDelete(g)}>
                <Trash2 className="h-4 w-4 text-destructive" />
              </Button>
            ) : null}
          </div>
        ) : null,
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('groups.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('groups.subtitle')}</p>
        </div>
        {canManage ? (
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('groups.create')}
          </Button>
        ) : null}
      </header>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(g) => g.id}
        loading={q.isLoading}
        emptyTitle={t('groups.empty')}
      />

      <WhatsappGroupFormModal open={modalOpen} onClose={() => setModalOpen(false)} group={editing} />
    </div>
  );
}
