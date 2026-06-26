import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTable, type Column } from '@/components/data/DataTable';
import { SearchInput } from '@/components/data/SearchInput';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { usePermissionGroups, useDeletePermissionGroup } from '../hooks';
import { PermissionGroupFormModal } from '../components/PermissionGroupFormModal';
import type { PermissionGroupData } from '@/types/rbac.types';

export default function PermissionGroupsPage() {
  const { t } = useTranslation('users');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('permission-groups.create');
  const canEdit = hasPermission('permission-groups.edit');
  const canDelete = hasPermission('permission-groups.delete');

  const [search, setSearch] = useState('');
  const [modalGroup, setModalGroup] = useState<PermissionGroupData | null>(null);
  const [modalOpen, setModalOpen] = useState(false);

  const q = usePermissionGroups();
  const del = useDeletePermissionGroup();

  const rows = useMemo(() => {
    const data = q.data ?? [];
    const term = search.trim().toLowerCase();
    if (!term) return data;
    return data.filter(
      (g) =>
        g.slug.toLowerCase().includes(term) ||
        g.display_name.toLowerCase().includes(term),
    );
  }, [q.data, search]);

  const openCreate = () => {
    setModalGroup(null);
    setModalOpen(true);
  };
  const openEdit = (g: PermissionGroupData) => {
    setModalGroup(g);
    setModalOpen(true);
  };

  const onDelete = async (g: PermissionGroupData) => {
    if (
      await confirm({
        title: t('groups.confirm.deleteTitle'),
        text: t('groups.confirm.deleteText'),
        confirmText: t('users.confirm.yes'),
        cancelText: t('common.cancel'),
      })
    )
      del.mutate(g.id);
  };

  const columns: Column<PermissionGroupData>[] = [
    {
      key: 'group',
      header: t('groups.col.group'),
      render: (g) => (
        <div className="min-w-0">
          <p className="font-medium">{g.display_name}</p>
          {g.description ? (
            <p className="truncate text-xs text-muted-foreground">{g.description}</p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'slug',
      header: t('groups.col.slug'),
      render: (g) => (
        <span className="font-mono text-xs text-muted-foreground">{g.slug}</span>
      ),
    },
    {
      key: 'permissions',
      header: t('groups.col.permissions'),
      align: 'center',
      render: (g) => <span className="text-sm">{g.permissions_count}</span>,
    },
    {
      key: 'order',
      header: t('groups.col.order'),
      align: 'center',
      render: (g) => <span className="text-sm text-muted-foreground">{g.sort_order}</span>,
    },
    {
      key: 'system',
      header: t('groups.col.system'),
      align: 'center',
      render: (g) =>
        g.is_system ? <Badge variant="muted">{t('common.systemBadge')}</Badge> : '—',
    },
    {
      key: 'actions',
      header: t('groups.col.actions'),
      align: 'end',
      render: (g) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {canEdit ? (
              <DropdownMenuItem onClick={() => openEdit(g)}>
                <Pencil className="h-4 w-4" />
                {t('common.edit')}
              </DropdownMenuItem>
            ) : null}
            {canDelete && !g.is_system ? (
              <DropdownMenuItem
                onClick={() => onDelete(g)}
                className="text-destructive focus:text-destructive"
              >
                <Trash2 className="h-4 w-4" />
                {t('common.delete')}
              </DropdownMenuItem>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
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
        {canCreate ? (
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('groups.new')}
          </Button>
        ) : null}
      </header>

      <div className="rounded-2xl border border-border bg-background p-3">
        <SearchInput
          value={search}
          onDebouncedChange={setSearch}
          placeholder={t('groups.searchPlaceholder')}
        />
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(g) => g.id}
        loading={q.isLoading}
      />

      <PermissionGroupFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        group={modalGroup}
      />
    </div>
  );
}
