import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { MoreHorizontal, Pencil, Tag as TagIcon, Trash2, X } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Modal } from '@/components/ui/modal';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { useAuth } from '@/hooks/useAuth';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useToast } from '@/hooks/useToast';
import { useDeleteTag, useManagedTags, useUpdateTag } from '../hooks';
import type { ContentLocale, ManagedTag, TagsListParams } from '@/types/content.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const PER_PAGE = 20;

/**
 * إدارة الوسوم (lوحة الإدارة) — قائمة مرقّمة بعدّاد الاستخدام مع إعادة تسمية (modal
 * ثنائي اللغة) وحذف. يستهلك نقاط Phase 1 ويعيد استخدام DataTable/Modal القائمين.
 */
export default function TagsPage() {
  const { t, i18n } = useTranslation('content');
  const { hasPermission } = useAuth();
  const { confirm, success } = useToast();

  const canEdit = hasPermission('tags.edit');
  const canDelete = hasPermission('tags.delete');

  const [params, setParams] = useState<TagsListParams>({
    page: 1,
    per_page: PER_PAGE,
    q: '',
    locale: 'ar',
  });
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  useEffect(() => {
    if (debouncedSearch === params.q) return;
    setParams((prev) => ({ ...prev, q: debouncedSearch, page: 1 }));
  }, [debouncedSearch, params.q]);

  const q = useManagedTags(params);
  const del = useDeleteTag();
  const update = useUpdateTag();

  const patch = (p: Partial<TagsListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  // ─── Rename modal ───
  const [editing, setEditing] = useState<ManagedTag | null>(null);
  const [nameAr, setNameAr] = useState('');
  const [nameEn, setNameEn] = useState('');

  const openRename = (tag: ManagedTag) => {
    setEditing(tag);
    setNameAr(tag.name.ar ?? '');
    setNameEn(tag.name.en ?? '');
  };
  const closeRename = () => setEditing(null);

  const submitRename = () => {
    if (!editing) return;
    const ar = nameAr.trim();
    const en = nameEn.trim();
    if (!ar && !en) return;
    update.mutate(
      { id: editing.id, payload: { name: { ar, en } } },
      {
        onSuccess: () => {
          success(t('tags.renamed'));
          closeRename();
        },
      },
    );
  };

  const tagName = (r: ManagedTag) => r.name[i18n.language] ?? r.name.ar ?? r.name.en ?? '—';
  const tagSlug = (r: ManagedTag) => r.slug[params.locale] ?? r.slug.ar ?? r.slug.en ?? '';

  const onDelete = async (r: ManagedTag) => {
    if (
      await confirm({
        title: t('tags.confirm.deleteTitle'),
        text: t('tags.confirm.deleteText', { name: tagName(r) }),
        confirmText: t('tags.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(r.id);
  };

  const columns: Column<ManagedTag>[] = [
    {
      key: 'name',
      header: t('tags.col.name'),
      render: (r) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{tagName(r)}</p>
          {tagSlug(r) ? (
            <p className="truncate text-xs text-muted-foreground" dir="ltr">
              /{tagSlug(r)}
            </p>
          ) : null}
        </div>
      ),
    },
    {
      key: 'usage',
      header: t('tags.col.usage'),
      align: 'center',
      render: (r) => <Badge variant="muted">{r.usage_count}</Badge>,
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (r) =>
        canEdit || canDelete ? (
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon" className="h-8 w-8">
                <MoreHorizontal className="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
              {canEdit ? (
                <DropdownMenuItem onClick={() => openRename(r)}>
                  <Pencil className="h-4 w-4" />
                  {t('tags.action.rename')}
                </DropdownMenuItem>
              ) : null}
              {canDelete ? (
                <>
                  {canEdit ? <DropdownMenuSeparator /> : null}
                  <DropdownMenuItem
                    onClick={() => void onDelete(r)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('tags.action.delete')}
                  </DropdownMenuItem>
                </>
              ) : null}
            </DropdownMenuContent>
          </DropdownMenu>
        ) : null,
    },
  ];

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-2">
        <TagIcon className="h-6 w-6 text-muted-foreground" />
        <div>
          <h1 className="text-2xl font-bold">{t('tags.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('tags.subtitle')}</p>
        </div>
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={t('tags.filter.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.locale}
          onChange={(e) => patch({ locale: e.target.value as ContentLocale })}
        >
          <option value="ar">{t('articles.locale.ar')}</option>
          <option value="en">{t('articles.locale.en')}</option>
        </select>
        {searchInput ? (
          <Button variant="outline" size="sm" onClick={() => setSearchInput('')}>
            <X className="h-4 w-4" />
            {t('tags.filter.reset')}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(r) => r.id}
        loading={q.isLoading}
        emptyTitle={t('tags.empty.title')}
        emptyDescription={t('tags.empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}

      <Modal
        open={editing !== null}
        onClose={closeRename}
        title={t('tags.rename.title')}
        footer={
          <>
            <Button variant="outline" onClick={closeRename} disabled={update.isPending}>
              {t('common.cancel', { ns: 'common' })}
            </Button>
            <Button
              onClick={submitRename}
              disabled={update.isPending || (!nameAr.trim() && !nameEn.trim())}
            >
              {update.isPending ? t('tags.rename.saving') : t('tags.rename.save')}
            </Button>
          </>
        }
      >
        <div className="grid gap-4">
          <div>
            <Label htmlFor="tag-ar">{t('tags.rename.nameAr')}</Label>
            <Input
              id="tag-ar"
              value={nameAr}
              onChange={(e) => setNameAr(e.target.value)}
              maxLength={60}
              dir="rtl"
            />
          </div>
          <div>
            <Label htmlFor="tag-en">{t('tags.rename.nameEn')}</Label>
            <Input
              id="tag-en"
              value={nameEn}
              onChange={(e) => setNameEn(e.target.value)}
              maxLength={60}
              dir="ltr"
            />
          </div>
        </div>
      </Modal>
    </div>
  );
}
