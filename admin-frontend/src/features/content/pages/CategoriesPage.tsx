import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  ChevronsDownUp,
  ChevronsUpDown,
  Eye,
  EyeOff,
  Plus,
  RotateCcw,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState, ErrorState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useBulkUpdateCategories,
  useCategories,
  useDeleteCategory,
  useForceDeleteCategory,
  useMoveCategory,
  useRestoreCategory,
  useTrashedCategories,
  useUpdateCategory,
} from '../hooks';
import { CategoryTreeHeader, CategoryTreeNode } from '../components/CategoryTreeNode';
import { CategoryFormModal } from '../components/CategoryFormModal';
import type {
  CategoryBulkPayload,
  CategoryData,
  CategoryScope,
  CategoryStatus,
  ContentLocale,
} from '@/types/content.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

type VisFilter = '' | 'show_in_header' | 'show_in_body' | 'show_in_footer';

/** Collect ids of every node that has children (for collapse-all). */
function collectParentIds(nodes: CategoryData[], acc: number[] = []): number[] {
  for (const n of nodes) {
    const children = Array.isArray(n.children) ? n.children : [];
    if (children.length > 0) {
      acc.push(n.id);
      collectParentIds(children, acc);
    }
  }
  return acc;
}

export default function CategoriesPage() {
  const { t, i18n } = useTranslation('content');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('categories.create');
  const canEdit = hasPermission('categories.edit');
  const canDelete = hasPermission('categories.delete');
  const canRestore = hasPermission('categories.restore');
  const canForceDelete = hasPermission('categories.force_delete');
  const canSeeTrash = canRestore || canForceDelete;

  // ─── View: active tree vs trash ─────────────────────────────────────────
  const [trashView, setTrashView] = useState(false);

  // ─── Filters ──────────────────────────────────────────────────────────
  const [search, setSearch] = useState('');
  const [localeFilter, setLocaleFilter] = useState<'' | ContentLocale>('');
  const [scopeFilter, setScopeFilter] = useState<'' | CategoryScope>('');
  const [statusFilter, setStatusFilter] = useState<'' | CategoryStatus>('');
  const [visFilter, setVisFilter] = useState<VisFilter>('');

  // ─── Tree state ───────────────────────────────────────────────────────
  const [collapsed, setCollapsed] = useState<Set<number>>(new Set());
  const [selected, setSelected] = useState<Set<number>>(new Set());

  // ─── Modal ────────────────────────────────────────────────────────────
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<CategoryData | null>(null);
  const [parentForNew, setParentForNew] = useState<CategoryData | null>(null);

  const q = useCategories();
  const del = useDeleteCategory();
  const update = useUpdateCategory();
  const move = useMoveCategory();
  const bulk = useBulkUpdateCategories();
  const trashedQ = useTrashedCategories(trashView);
  const restore = useRestoreCategory();
  const forceDel = useForceDeleteCategory();

  const [trashSelected, setTrashSelected] = useState<Set<number>>(new Set());
  const toggleTrashSelect = (id: number) =>
    setTrashSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  const clearTrashSelection = () => setTrashSelected(new Set());

  const fmtDate = (v: string | null) =>
    v ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(v)) : '—';

  const onForceDelete = async (id: number, name: string) => {
    if (
      await confirm({
        title: t('categories.trash.forceTitle'),
        text: t('categories.trash.forceText', { name }),
        confirmText: t('categories.trash.forceConfirm'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      forceDel.mutate(id);
  };

  const bulkRestore = async () => {
    if (trashSelected.size === 0) return;
    await Promise.allSettled([...trashSelected].map((id) => restore.mutateAsync(id)));
    clearTrashSelection();
  };

  const bulkForceDelete = async () => {
    if (trashSelected.size === 0) return;
    if (
      !(await confirm({
        title: t('categories.trash.bulkDeleteTitle'),
        text: t('categories.trash.bulkDeleteText', { count: trashSelected.size }),
        confirmText: t('categories.trash.forceConfirm'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    )
      return;
    await Promise.allSettled([...trashSelected].map((id) => forceDel.mutateAsync(id)));
    clearTrashSelection();
  };

  const roots = useMemo(() => (q.data ?? []).filter((c) => c.parent_id === null), [q.data]);

  const filtersActive =
    search.trim() !== '' || !!localeFilter || !!scopeFilter || !!statusFilter || !!visFilter;

  // Keep a node when it matches, OR when a descendant matches (kept as an
  // ancestor for context). A direct match keeps its full subtree.
  const filteredRoots = useMemo(() => {
    if (!filtersActive) return roots;
    const term = search.trim().toLowerCase();

    const matches = (n: CategoryData): boolean => {
      if (term && !n.name.toLowerCase().includes(term) && !n.slug.toLowerCase().includes(term)) {
        return false;
      }
      if (localeFilter && n.locale !== localeFilter) return false;
      if (scopeFilter && n.scope !== scopeFilter) return false;
      if (statusFilter && n.status !== statusFilter) return false;
      if (visFilter && !n[visFilter]) return false;
      return true;
    };

    const walk = (nodes: CategoryData[]): CategoryData[] => {
      const out: CategoryData[] = [];
      for (const n of nodes) {
        const children = Array.isArray(n.children) ? n.children : [];
        if (matches(n)) {
          out.push(n); // full subtree
        } else {
          const fc = walk(children);
          if (fc.length > 0) out.push({ ...n, children: fc });
        }
      }
      return out;
    };

    return walk(roots);
  }, [roots, filtersActive, search, localeFilter, scopeFilter, statusFilter, visFilter]);

  const resetFilters = () => {
    setSearch('');
    setLocaleFilter('');
    setScopeFilter('');
    setStatusFilter('');
    setVisFilter('');
  };

  // ─── Tree controls ──────────────────────────────────────────────────────
  const toggleOpen = (id: number) =>
    setCollapsed((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const expandAll = () => setCollapsed(new Set());
  const collapseAll = () => setCollapsed(new Set(collectParentIds(roots)));

  // ─── Selection ────────────────────────────────────────────────────────
  const toggleSelect = (id: number) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  const clearSelection = () => setSelected(new Set());

  // ─── Mutations ────────────────────────────────────────────────────────
  const quickToggle = (n: CategoryData, patch: CategoryBulkPayload) =>
    update.mutate({ id: n.id, payload: patch });

  const onMove = (n: CategoryData, direction: 'up' | 'down') =>
    move.mutate({ id: n.id, direction });

  const openArticles = (n: CategoryData) => navigate(`${paths.articles}?category=${n.id}`);

  const bulkStatus = (status: CategoryStatus) => {
    if (selected.size === 0) return;
    bulk.mutate(
      { ids: [...selected], payload: { status } },
      { onSuccess: () => clearSelection() },
    );
  };

  const bulkDelete = async () => {
    if (selected.size === 0) return;
    if (
      !(await confirm({
        title: t('categories.bulk.deleteTitle'),
        text: t('categories.bulk.deleteText', { count: selected.size }),
        confirmText: t('categories.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    )
      return;
    await Promise.allSettled([...selected].map((id) => del.mutateAsync(id)));
    clearSelection();
  };

  const openCreate = () => {
    setEditing(null);
    setParentForNew(null);
    setModalOpen(true);
  };
  const openCreateChild = (parent: CategoryData) => {
    setEditing(null);
    setParentForNew(parent);
    setModalOpen(true);
  };
  const openEdit = (n: CategoryData) => {
    setEditing(n);
    setParentForNew(null);
    setModalOpen(true);
  };
  const onDelete = async (n: CategoryData) => {
    if (
      await confirm({
        title: t('categories.confirm.deleteTitle'),
        text: t('categories.confirm.deleteText', { name: n.name }),
        confirmText: t('categories.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(n.id);
  };

  if (q.isError) {
    const err = q.error as { message?: string; status?: number } | null;
    const detail = err?.message
      ? err.status
        ? `[${err.status}] ${err.message}`
        : err.message
      : undefined;
    return <ErrorState message={detail} onRetry={() => void q.refetch()} />;
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('categories.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('categories.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          {canSeeTrash ? (
            <Button
              variant={trashView ? 'default' : 'outline'}
              onClick={() => {
                clearTrashSelection();
                setTrashView((v) => !v);
              }}
            >
              <Trash2 className="h-4 w-4" />
              {trashView ? t('categories.trash.exit') : t('categories.trash.title')}
            </Button>
          ) : null}
          {canCreate && !trashView ? (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" />
              {t('categories.new')}
            </Button>
          ) : null}
        </div>
      </header>

      {trashView ? (
        <>
          {trashSelected.size > 0 ? (
            <div className="flex flex-wrap items-center gap-3 border border-primary/40 bg-primary/5 p-3">
              <span className="text-sm font-medium">
                {t('categories.trash.selected', { count: trashSelected.size })}
              </span>
              {canRestore ? (
                <Button variant="outline" size="sm" onClick={bulkRestore} disabled={restore.isPending}>
                  <RotateCcw className="h-4 w-4" />
                  {t('categories.trash.bulkRestore')}
                </Button>
              ) : null}
              {canForceDelete ? (
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive"
                  onClick={bulkForceDelete}
                  disabled={forceDel.isPending}
                >
                  <Trash2 className="h-4 w-4" />
                  {t('categories.trash.bulkForceDelete')}
                </Button>
              ) : null}
              <Button variant="ghost" size="sm" className="ms-auto" onClick={clearTrashSelection}>
                <X className="h-4 w-4" />
                {t('categories.bulk.clear')}
              </Button>
            </div>
          ) : null}

          <div className="border border-border bg-background">
            {trashedQ.isLoading ? (
              <div className="space-y-2 p-3">
                {Array.from({ length: 4 }).map((_, i) => (
                  <Skeleton key={i} className="h-10 w-full" />
                ))}
              </div>
            ) : (trashedQ.data ?? []).length === 0 ? (
              <EmptyState title={t('categories.trash.empty')} />
            ) : (
              <>
                <div className="flex items-center gap-2 border-b border-border bg-muted/40 px-3 py-2 text-[11px] font-bold uppercase text-muted-foreground">
                  <span className="w-4 shrink-0" />
                  <span className="min-w-0 flex-1">{t('categories.col.name')}</span>
                  <span className="hidden w-12 shrink-0 text-center uppercase sm:inline">
                    {t('categories.col.locale')}
                  </span>
                  <span className="hidden w-28 shrink-0 text-center md:inline">
                    {t('categories.trash.deletedAt')}
                  </span>
                  <span className="w-40 shrink-0" />
                </div>
                {(trashedQ.data ?? []).map((item) => (
                  <div
                    key={item.id}
                    className="flex items-center gap-2 border-b border-border px-3 py-2.5 hover:bg-accent/40"
                  >
                    <input
                      type="checkbox"
                      checked={trashSelected.has(item.id)}
                      onChange={() => toggleTrashSelect(item.id)}
                      className="h-4 w-4 shrink-0"
                      aria-label={t('categories.bulk.select')}
                    />
                    <div className="min-w-0 flex-1">
                      <p className="truncate font-medium">{item.name}</p>
                      <p className="truncate text-xs text-muted-foreground">
                        /{item.slug}
                        {item.parent_name
                          ? ` · ${t('categories.trash.under', { name: item.parent_name })}`
                          : ''}
                      </p>
                    </div>
                    <span className="hidden w-12 shrink-0 text-center text-xs uppercase text-muted-foreground sm:inline">
                      {item.locale}
                    </span>
                    <span className="hidden w-28 shrink-0 text-center text-xs text-muted-foreground md:inline">
                      {fmtDate(item.deleted_at)}
                    </span>
                    <div className="flex w-40 shrink-0 items-center justify-end gap-1">
                      {canRestore ? (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => restore.mutate(item.id)}
                          disabled={restore.isPending}
                        >
                          <RotateCcw className="h-4 w-4" />
                          {t('categories.trash.restore')}
                        </Button>
                      ) : null}
                      {canForceDelete ? (
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive"
                          onClick={() => onForceDelete(item.id, item.name)}
                          disabled={forceDel.isPending}
                          title={t('categories.trash.forceDelete')}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      ) : null}
                    </div>
                  </div>
                ))}
              </>
            )}
          </div>
        </>
      ) : (
        <>
      {/* ─── Toolbar: search + filters + expand controls ─── */}
      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <div className="relative min-w-[200px] flex-1">
          <Search className="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground start-3" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t('categories.filter.search')}
            className="ps-9"
          />
        </div>

        <select
          className={selectCls}
          value={localeFilter}
          onChange={(e) => setLocaleFilter(e.target.value as '' | ContentLocale)}
        >
          <option value="">{t('categories.filter.localeAll')}</option>
          <option value="ar">{t('articles.locale.ar')}</option>
          <option value="en">{t('articles.locale.en')}</option>
        </select>

        <select
          className={selectCls}
          value={scopeFilter}
          onChange={(e) => setScopeFilter(e.target.value as '' | CategoryScope)}
        >
          <option value="">{t('categories.filter.scopeAll')}</option>
          <option value="news">{t('categories.scope.news')}</option>
          <option value="opinion">{t('categories.scope.opinion')}</option>
          <option value="both">{t('categories.scope.both')}</option>
        </select>

        <select
          className={selectCls}
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as '' | CategoryStatus)}
        >
          <option value="">{t('categories.filter.statusAll')}</option>
          <option value="active">{t('categories.status.active')}</option>
          <option value="hidden">{t('categories.status.hidden')}</option>
        </select>

        <select
          className={selectCls}
          value={visFilter}
          onChange={(e) => setVisFilter(e.target.value as VisFilter)}
        >
          <option value="">{t('categories.filter.visAll')}</option>
          <option value="show_in_header">{t('categories.form.show_in_header')}</option>
          <option value="show_in_body">{t('categories.form.show_in_body')}</option>
          <option value="show_in_footer">{t('categories.form.show_in_footer')}</option>
        </select>

        {filtersActive ? (
          <Button variant="outline" size="sm" onClick={resetFilters}>
            <X className="h-4 w-4" />
            {t('categories.filter.reset')}
          </Button>
        ) : null}

        <div className="ms-auto flex items-center gap-1">
          <Button variant="ghost" size="sm" onClick={expandAll} title={t('categories.expandAll')}>
            <ChevronsUpDown className="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="sm" onClick={collapseAll} title={t('categories.collapseAll')}>
            <ChevronsDownUp className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* ─── Bulk action bar ─── */}
      {canEdit && selected.size > 0 ? (
        <div className="flex flex-wrap items-center gap-3 border border-primary/40 bg-primary/5 p-3">
          <span className="text-sm font-medium">
            {t('categories.bulk.selected', { count: selected.size })}
          </span>
          <Button variant="outline" size="sm" onClick={() => bulkStatus('active')} disabled={bulk.isPending}>
            <Eye className="h-4 w-4" />
            {t('categories.bulk.activate')}
          </Button>
          <Button variant="outline" size="sm" onClick={() => bulkStatus('hidden')} disabled={bulk.isPending}>
            <EyeOff className="h-4 w-4" />
            {t('categories.bulk.hide')}
          </Button>
          {canDelete ? (
            <Button
              variant="outline"
              size="sm"
              onClick={bulkDelete}
              className="text-destructive"
              disabled={del.isPending}
            >
              <Trash2 className="h-4 w-4" />
              {t('categories.bulk.delete')}
            </Button>
          ) : null}
          <Button variant="ghost" size="sm" className="ms-auto" onClick={clearSelection}>
            <X className="h-4 w-4" />
            {t('categories.bulk.clear')}
          </Button>
        </div>
      ) : null}

      <div className="border border-border bg-background">
        {q.isLoading ? (
          <div className="space-y-2 p-3">
            {Array.from({ length: 5 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full" />
            ))}
          </div>
        ) : filteredRoots.length === 0 ? (
          <EmptyState title={filtersActive ? t('categories.noMatches') : t('categories.empty')} />
        ) : (
          <>
            <CategoryTreeHeader canEdit={canEdit} />
            {filteredRoots.map((root, i) => (
            <CategoryTreeNode
              key={root.id}
              node={root}
              depth={0}
              siblingIndex={i}
              siblingCount={filteredRoots.length}
              canEdit={canEdit}
              canCreate={canCreate}
              canDelete={canDelete}
              collapsed={collapsed}
              forceOpen={filtersActive}
              selected={selected}
              onToggleOpen={toggleOpen}
              onToggleSelect={toggleSelect}
              onEdit={openEdit}
              onCreateChild={openCreateChild}
              onDelete={onDelete}
              onMove={onMove}
              onQuickToggle={quickToggle}
              onOpenArticles={openArticles}
            />
            ))}
          </>
        )}
      </div>
        </>
      )}

      <CategoryFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        category={editing}
        parent={parentForNew}
        allCategories={q.data ?? []}
      />
    </div>
  );
}
