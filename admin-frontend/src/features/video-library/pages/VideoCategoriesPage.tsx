import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronsDownUp, ChevronsUpDown, Plus, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { EmptyState, ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useDeleteVideoCategory,
  useMoveVideoCategory,
  useUpdateVideoCategory,
  useVideoCategoryTree,
} from '../hooks';
import { VideoCategoryTreeHeader, VideoCategoryTreeNode } from '../components/VideoCategoryTreeNode';
import { VideoCategoryFormModal } from '../components/VideoCategoryFormModal';
import type { ContentLocale } from '@/types/content.types';
import type { VideoCategoryData } from '@/types/videoLibrary.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

function collectParentIds(nodes: VideoCategoryData[], acc: number[] = []): number[] {
  for (const n of nodes) {
    const children = Array.isArray(n.children) ? n.children : [];
    if (children.length > 0) {
      acc.push(n.id);
      collectParentIds(children, acc);
    }
  }
  return acc;
}

export default function VideoCategoriesPage() {
  const { t } = useTranslation('videoLibrary');
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canManage = hasPermission('video-categories.manage');

  const [search, setSearch] = useState('');
  const [localeFilter, setLocaleFilter] = useState<'' | ContentLocale>('');
  const [statusFilter, setStatusFilter] = useState<'' | 'active' | 'inactive'>('');
  const [collapsed, setCollapsed] = useState<Set<number>>(new Set());

  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<VideoCategoryData | null>(null);
  const [parentForNew, setParentForNew] = useState<VideoCategoryData | null>(null);

  const q = useVideoCategoryTree();
  const del = useDeleteVideoCategory();
  const update = useUpdateVideoCategory();
  const move = useMoveVideoCategory();

  const roots = q.data ?? [];
  const filtersActive = search.trim() !== '' || !!localeFilter || !!statusFilter;

  const filteredRoots = useMemo(() => {
    if (!filtersActive) return roots;
    const term = search.trim().toLowerCase();
    const matches = (n: VideoCategoryData): boolean => {
      if (term && !n.name.toLowerCase().includes(term) && !n.slug.toLowerCase().includes(term)) return false;
      if (localeFilter && n.locale !== localeFilter) return false;
      if (statusFilter === 'active' && !n.is_active) return false;
      if (statusFilter === 'inactive' && n.is_active) return false;
      return true;
    };
    const walk = (nodes: VideoCategoryData[]): VideoCategoryData[] => {
      const out: VideoCategoryData[] = [];
      for (const n of nodes) {
        const children = Array.isArray(n.children) ? n.children : [];
        if (matches(n)) out.push(n);
        else {
          const fc = walk(children);
          if (fc.length > 0) out.push({ ...n, children: fc });
        }
      }
      return out;
    };
    return walk(roots);
  }, [roots, filtersActive, search, localeFilter, statusFilter]);

  const toggleOpen = (id: number) =>
    setCollapsed((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  const expandAll = () => setCollapsed(new Set());
  const collapseAll = () => setCollapsed(new Set(collectParentIds(roots)));

  const resetFilters = () => {
    setSearch('');
    setLocaleFilter('');
    setStatusFilter('');
  };

  const openCreate = () => {
    setEditing(null);
    setParentForNew(null);
    setModalOpen(true);
  };
  const openCreateChild = (parent: VideoCategoryData) => {
    setEditing(null);
    setParentForNew(parent);
    setModalOpen(true);
  };
  const openEdit = (n: VideoCategoryData) => {
    setEditing(n);
    setParentForNew(null);
    setModalOpen(true);
  };
  const onDelete = async (n: VideoCategoryData) => {
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
  const onToggleActive = (n: VideoCategoryData) => update.mutate({ id: n.id, payload: { is_active: !n.is_active } });
  const onMove = (n: VideoCategoryData, direction: 'up' | 'down') => move.mutate({ id: n.id, direction });

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

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <div className="relative min-w-[200px] flex-1">
          <Search className="pointer-events-none absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground start-3" />
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder={t('categories.filter.search')} className="ps-9" />
        </div>
        <select className={selectCls} value={localeFilter} onChange={(e) => setLocaleFilter(e.target.value as '' | ContentLocale)}>
          <option value="">{t('categories.filter.localeAll')}</option>
          <option value="ar">{t('locale.ar')}</option>
          <option value="en">{t('locale.en')}</option>
        </select>
        <select className={selectCls} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value as '' | 'active' | 'inactive')}>
          <option value="">{t('categories.filter.statusAll')}</option>
          <option value="active">{t('categories.status.active')}</option>
          <option value="inactive">{t('categories.status.inactive')}</option>
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
            <VideoCategoryTreeHeader canEdit={canManage} />
            {filteredRoots.map((root, i) => (
              <VideoCategoryTreeNode
                key={root.id}
                node={root}
                depth={0}
                siblingIndex={i}
                siblingCount={filteredRoots.length}
                canEdit={canManage}
                canCreate={canManage}
                canDelete={canManage}
                collapsed={collapsed}
                forceOpen={filtersActive}
                onToggleOpen={toggleOpen}
                onEdit={openEdit}
                onCreateChild={openCreateChild}
                onDelete={onDelete}
                onMove={onMove}
                onToggleActive={onToggleActive}
              />
            ))}
          </>
        )}
      </div>

      <VideoCategoryFormModal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        category={editing}
        parent={parentForNew}
        allCategories={q.data ?? []}
      />
    </div>
  );
}
