import { useState } from 'react';
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
import { Pagination } from '@/components/data/Pagination';
import { SearchInput } from '@/components/data/SearchInput';
import { ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useRoles, useDeleteRole } from '../hooks';
import { RoleFormModal } from '../components/RoleFormModal';
import type { RoleData } from '@/types/rbac.types';

const PER_PAGE = 15;

export default function RolesPage() {
  const { t } = useTranslation('users');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('roles.create');
  const canEdit = hasPermission('roles.edit');
  const canDelete = hasPermission('roles.delete');

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [modalRole, setModalRole] = useState<RoleData | null>(null);
  const [modalOpen, setModalOpen] = useState(false);

  const q = useRoles({ page, per_page: PER_PAGE, search });
  const del = useDeleteRole();

  const openCreate = () => {
    setModalRole(null);
    setModalOpen(true);
  };
  const openEdit = (r: RoleData) => {
    setModalRole(r);
    setModalOpen(true);
  };

  const onDelete = async (r: RoleData) => {
    if (
      await confirm({
        title: t('roles.confirm.deleteTitle'),
        text: t('roles.confirm.deleteText'),
        confirmText: t('users.confirm.yes'),
        cancelText: t('common.cancel'),
      })
    )
      del.mutate(r.id);
  };

  const columns: Column<RoleData>[] = [
    {
      key: 'role',
      header: t('roles.col.role'),
      render: (r) => (
        <div className="min-w-0">
          <p className="font-medium">{r.display_name}</p>
          <p className="truncate text-xs text-muted-foreground">{r.name}</p>
        </div>
      ),
    },
    {
      key: 'users',
      header: t('roles.col.users'),
      align: 'center',
      render: (r) => <span className="text-sm">{r.users_count}</span>,
    },
    {
      key: 'permissions',
      header: t('roles.col.permissions'),
      align: 'center',
      render: (r) => <span className="text-sm">{r.permissions_count}</span>,
    },
    {
      key: 'system',
      header: t('roles.col.system'),
      align: 'center',
      render: (r) =>
        r.is_system ? <Badge variant="muted">{t('common.systemBadge')}</Badge> : <span>—</span>,
    },
    {
      key: 'actions',
      header: t('roles.col.actions'),
      align: 'end',
      render: (r) => {
        const protectedRole = r.name === 'super_admin';
        return (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {canEdit ? (
                <DropdownMenuItem onClick={() => openEdit(r)}>
                  <Pencil className="h-4 w-4" />
                  {t('common.edit')}
                </DropdownMenuItem>
              ) : null}
              {canDelete && !protectedRole ? (
                <DropdownMenuItem
                  onClick={() => onDelete(r)}
                  className="text-destructive focus:text-destructive"
                >
                  <Trash2 className="h-4 w-4" />
                  {t('common.delete')}
                </DropdownMenuItem>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
        );
      },
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('roles.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('roles.subtitle')}</p>
        </div>
        {canCreate ? (
          <Button onClick={openCreate}>
            <Plus className="h-4 w-4" />
            {t('roles.new')}
          </Button>
        ) : null}
      </header>

      <div className="rounded-2xl border border-border bg-background p-3">
        <SearchInput
          value={search}
          onDebouncedChange={(v) => {
            setSearch(v);
            setPage(1);
          }}
          placeholder={t('roles.searchPlaceholder')}
        />
      </div>

      <DataTable columns={columns} rows={q.data?.data ?? []} rowKey={(r) => r.id} loading={q.isLoading} />

      {q.data ? <Pagination meta={q.data.pagination} onPage={setPage} /> : null}

      <RoleFormModal open={modalOpen} onClose={() => setModalOpen(false)} role={modalRole} />
    </div>
  );
}
