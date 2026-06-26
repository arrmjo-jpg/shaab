import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ArchiveRestore,
  ChevronDown,
  ChevronUp,
  Eye,
  EyeOff,
  MoreHorizontal,
  Pencil,
  Plus,
  Trash2,
  User,
  X,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useToast } from '@/hooks/useToast';
import {
  useDeleteTeamMember,
  useForceDeleteTeamMember,
  useReorderTeamMembers,
  useRestoreTeamMember,
  useTeamMembers,
  useToggleTeamMemberStatus,
} from '../teamMembers.hooks';
import type { TeamMemberData, TeamMembersListParams, TeamMemberStatus } from '@/types/team.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS_TONE: Record<TeamMemberStatus, 'success' | 'muted'> = {
  active: 'success',
  inactive: 'muted',
};

const PER_PAGE = 15;

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

export default function TeamMembersPage() {
  const { t, i18n } = useTranslation('team');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('team.create');
  const canEdit = hasPermission('team.edit');
  const canDelete = hasPermission('team.delete');
  const canRestore = hasPermission('team.restore');
  const canForceDelete = hasPermission('team.force_delete');
  const canSeeTrash = canRestore || canForceDelete;

  const [params, setParams] = useState<TeamMembersListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    status: '',
    department: '',
    sort: 'sort_order',
    trashed: '',
  });
  // قيمة بحث محلية مع debounce — تقلّل ضغط الـ API عند الكتابة السريعة.
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  useEffect(() => {
    if (debouncedSearch === params.search) return;
    setParams((prev) => ({ ...prev, search: debouncedSearch, page: 1 }));
  }, [debouncedSearch, params.search]);

  const q = useTeamMembers(params);
  const del = useDeleteTeamMember();
  const restore = useRestoreTeamMember();
  const forceDel = useForceDeleteTeamMember();
  const toggle = useToggleTeamMemberStatus();
  const reorder = useReorderTeamMembers();

  const rows = q.data?.data ?? [];

  // أقسام مقترَحة (datalist) — مشتقّة من الصفحة الحالية لتسهيل التصفية بمطابقة دقيقة.
  const departments = useMemo(
    () => Array.from(new Set(rows.map((r) => r.department).filter(Boolean))) as string[],
    [rows],
  );

  const patch = (p: Partial<TeamMembersListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const inTrash = params.trashed === 'only';
  // الترتيب اليدوي (↑↓) متاح فقط عند الفرز بـ sort_order وخارج السلّة وبصلاحية التعديل.
  const canManualOrder = canEdit && !inTrash && params.sort === 'sort_order';

  const move = (index: number, dir: -1 | 1) => {
    const target = index + dir;
    if (target < 0 || target >= rows.length) return;
    const next = [...rows];
    [next[index], next[target]] = [next[target], next[index]];
    reorder.mutate(next.map((r) => r.id));
  };

  const onDelete = async (r: TeamMemberData) => {
    if (
      await confirm({
        title: t('confirm.deleteTitle'),
        text: t('confirm.deleteText', { name: r.name }),
        confirmText: t('confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(r.id);
  };

  const onForceDelete = async (r: TeamMemberData) => {
    if (
      await confirm({
        title: t('confirm.forceTitle'),
        text: t('confirm.forceText', { name: r.name }),
        confirmText: t('confirm.forceYes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      forceDel.mutate(r.id);
  };

  const columns: Column<TeamMemberData>[] = [
    {
      key: 'avatar',
      header: '',
      render: (r) => (
        <div className="flex h-11 w-11 items-center justify-center overflow-hidden border border-border bg-muted">
          {/* thumbnail لا الأصل — أداء + null-safe */}
          {r.avatar?.thumb ?? r.avatar?.url ? (
            <img
              src={(r.avatar?.thumb ?? r.avatar?.url) as string}
              alt=""
              loading="lazy"
              className="h-full w-full object-cover"
            />
          ) : (
            <User className="h-4 w-4 text-muted-foreground" />
          )}
        </div>
      ),
    },
    {
      key: 'name',
      header: t('col.name'),
      render: (r) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{r.name}</p>
          <p className="truncate text-xs text-muted-foreground">/{r.slug}</p>
        </div>
      ),
    },
    {
      key: 'jobTitle',
      header: t('col.jobTitle'),
      render: (r) => <span className="truncate text-sm">{r.job_title}</span>,
    },
    {
      key: 'department',
      header: t('col.department'),
      render: (r) =>
        r.department ? (
          <Badge variant="muted">{r.department}</Badge>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
    },
    {
      key: 'status',
      header: t('col.status'),
      render: (r) => <Badge variant={STATUS_TONE[r.status]}>{t(`status.${r.status}`)}</Badge>,
    },
    {
      key: 'order',
      header: t('col.order'),
      align: 'center',
      render: (r) => {
        const index = rows.findIndex((x) => x.id === r.id);
        if (!canManualOrder) {
          return <span className="text-xs tabular-nums text-muted-foreground">{r.sort_order}</span>;
        }
        return (
          <div className="flex items-center justify-center gap-0.5">
            <Button
              variant="ghost"
              size="icon"
              className="h-7 w-7"
              title={t('action.moveUp')}
              disabled={index <= 0 || reorder.isPending}
              onClick={() => move(index, -1)}
            >
              <ChevronUp className="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="icon"
              className="h-7 w-7"
              title={t('action.moveDown')}
              disabled={index >= rows.length - 1 || reorder.isPending}
              onClick={() => move(index, 1)}
            >
              <ChevronDown className="h-4 w-4" />
            </Button>
          </div>
        );
      },
    },
    {
      key: 'date',
      header: t('col.date'),
      render: (r) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">
          {fmtDate(r.created_at, i18n.language)}
        </span>
      ),
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (r) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {inTrash ? (
              <>
                {canRestore ? (
                  <DropdownMenuItem onClick={() => restore.mutate(r.id)}>
                    <ArchiveRestore className="h-4 w-4" />
                    {t('action.restore')}
                  </DropdownMenuItem>
                ) : null}
                {canForceDelete ? (
                  <DropdownMenuItem
                    onClick={() => void onForceDelete(r)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('action.forceDelete')}
                  </DropdownMenuItem>
                ) : null}
              </>
            ) : (
              <>
                {canEdit ? (
                  <DropdownMenuItem
                    onClick={() => navigate(paths.teamMembersEdit.replace(':id', String(r.id)))}
                  >
                    <Pencil className="h-4 w-4" />
                    {t('action.edit')}
                  </DropdownMenuItem>
                ) : null}
                {canEdit ? (
                  <DropdownMenuItem
                    onClick={() =>
                      toggle.mutate({
                        id: r.id,
                        status: r.status === 'active' ? 'inactive' : 'active',
                      })
                    }
                  >
                    {r.status === 'active' ? (
                      <>
                        <EyeOff className="h-4 w-4" />
                        {t('action.deactivate')}
                      </>
                    ) : (
                      <>
                        <Eye className="h-4 w-4" />
                        {t('action.activate')}
                      </>
                    )}
                  </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      onClick={() => void onDelete(r)}
                      className="text-destructive focus:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                      {t('action.delete')}
                    </DropdownMenuItem>
                  </>
                ) : null}
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  const hasFilters = Boolean(
    searchInput || params.status || params.department || params.trashed,
  );

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('title')}</h1>
          <p className="text-sm text-muted-foreground">{t('subtitle')}</p>
        </div>
        {canCreate ? (
          <Button onClick={() => navigate(paths.teamMembersCreate)}>
            <Plus className="h-4 w-4" />
            {t('new')}
          </Button>
        ) : null}
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={t('filter.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.status}
          onChange={(e) => patch({ status: e.target.value as TeamMembersListParams['status'] })}
        >
          <option value="">{t('filter.statusAll')}</option>
          <option value="active">{t('status.active')}</option>
          <option value="inactive">{t('status.inactive')}</option>
        </select>
        <input
          className={`${selectCls} min-w-[160px]`}
          list="team-departments"
          value={params.department}
          onChange={(e) => patch({ department: e.target.value })}
          placeholder={t('filter.departmentPlaceholder')}
        />
        <datalist id="team-departments">
          {departments.map((d) => (
            <option key={d} value={d} />
          ))}
        </datalist>
        <select
          className={selectCls}
          value={params.sort}
          onChange={(e) => patch({ sort: e.target.value as TeamMembersListParams['sort'] })}
        >
          <option value="sort_order">{t('filter.sortManual')}</option>
          <option value="-created_at">{t('filter.sortNewest')}</option>
          <option value="name">{t('filter.sortName')}</option>
        </select>
        {canSeeTrash ? (
          <select
            className={selectCls}
            value={params.trashed}
            onChange={(e) => patch({ trashed: e.target.value as TeamMembersListParams['trashed'] })}
          >
            <option value="">{t('filter.trashedNone')}</option>
            <option value="only">{t('filter.trashedOnly')}</option>
          </select>
        ) : null}
        {hasFilters ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => {
              setSearchInput('');
              setParams({
                page: 1,
                per_page: PER_PAGE,
                search: '',
                status: '',
                department: '',
                sort: 'sort_order',
                trashed: '',
              });
            }}
          >
            <X className="h-4 w-4" />
            {t('filter.reset')}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(r) => r.id}
        loading={q.isLoading}
        emptyTitle={inTrash ? t('empty.trashTitle') : t('empty.title')}
        emptyDescription={inTrash ? t('empty.trashDescription') : t('empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
    </div>
  );
}
