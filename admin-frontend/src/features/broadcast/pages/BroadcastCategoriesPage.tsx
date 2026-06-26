import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useBroadcastCategories, useDeleteBroadcastCategory } from '../hooks';
import { BroadcastCategoryFormModal } from '../components/BroadcastCategoryFormModal';
import type { BroadcastCategoryData } from '@/types/broadcast.types';

export default function BroadcastCategoriesPage() {
  const { t, i18n } = useTranslation('broadcast');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canManage = hasPermission('broadcast-categories.manage');

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<BroadcastCategoryData | null>(null);

  const q = useBroadcastCategories();
  const del = useDeleteBroadcastCategory();

  const rows = q.data ?? [];

  const openCreate = () => {
    setEditing(null);
    setModalOpen(true);
  };
  const openEdit = (c: BroadcastCategoryData) => {
    setEditing(c);
    setModalOpen(true);
  };
  const onDelete = async (c: BroadcastCategoryData) => {
    if (
      await confirm({
        title: t('categories.confirm.deleteTitle'),
        text: t('categories.confirm.deleteText', { name: c.name }),
        confirmText: t('categories.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(c.id);
  };

  const columns: Column<BroadcastCategoryData>[] = [
    {
      key: 'name',
      header: t('categories.col.name'),
      render: (c) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{c.name}</p>
          <p className="truncate text-xs text-muted-foreground">/{c.slug}</p>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('categories.col.status'),
      render: (c) =>
        c.is_active ? (
          <Badge variant="success">{t('categories.status.active')}</Badge>
        ) : (
          <Badge variant="muted">{t('categories.status.inactive')}</Badge>
        ),
    },
    {
      key: 'count',
      header: t('categories.col.count'),
      align: 'center',
      render: (c) => <span className="text-xs tabular-nums text-muted-foreground">{(c.broadcasts_count ?? 0).toLocaleString(i18n.language)}</span>,
    },
    {
      key: 'order',
      header: t('categories.col.order'),
      align: 'center',
      render: (c) => <span className="text-xs tabular-nums text-muted-foreground">{c.sort_order.toLocaleString(i18n.language)}</span>,
    },
    ...(canManage
      ? [
          {
            key: 'actions',
            header: '',
            align: 'end',
            render: (c: BroadcastCategoryData) => (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => openEdit(c)}>
                    <Pencil className="h-4 w-4" />
                    {t('categories.action.edit')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem onClick={() => void onDelete(c)} className="text-destructive focus:text-destructive">
                    <Trash2 className="h-4 w-4" />
                    {t('categories.action.delete')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            ),
          } as Column<BroadcastCategoryData>,
        ]
      : []),
  ];

  if (q.isError) {
    return <ErrorState onRetry={() => void q.refetch()} />;
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('categories.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('categories.subtitle')}</p>
        </div>
        {canManage ? (
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('categories.new')}
          </Button>
        ) : null}
      </header>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(c) => c.id}
        loading={q.isLoading}
        emptyTitle={t('categories.empty')}
        emptyDescription={t('categories.emptyDescription')}
      />

      <BroadcastCategoryFormModal open={modalOpen} onClose={() => setModalOpen(false)} category={editing} />
    </div>
  );
}
