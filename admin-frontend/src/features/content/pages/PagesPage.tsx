import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Archive,
  ArchiveRestore,
  MoreHorizontal,
  Pencil,
  Plus,
  Send,
  Trash2,
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
  useDeletePage,
  useForceDeletePage,
  usePages,
  useRestorePage,
  useTransitionPage,
} from '../pages.hooks';
import type { PageData, PagesListParams, PageStatus } from '@/types/content.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS_TONE: Record<PageStatus, 'success' | 'muted'> = {
  published: 'success',
  draft: 'muted',
  archived: 'muted',
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

export default function PagesPage() {
  const { t, i18n } = useTranslation('content');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('pages.create');
  const canEdit = hasPermission('pages.edit');
  const canPublish = hasPermission('pages.publish');
  const canArchive = hasPermission('pages.archive');
  const canDelete = hasPermission('pages.delete');
  const canRestore = hasPermission('pages.restore');
  const canForceDelete = hasPermission('pages.force_delete');
  const canSeeTrash = canRestore || canForceDelete;

  const [params, setParams] = useState<PagesListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    status: '',
    locale: '',
    show_in_header: '',
    show_in_footer: '',
    sort: '-created_at',
    trashed: '',
  });
  // قيمة بحث منفصلة محلية — تطبَّق على params بعد debounce لتقليل ضغط الـ API
  // عند الكتابة السريعة. باقي الفلاتر تنطبق فوراً (تغييرها قرار صريح).
  const [searchInput, setSearchInput] = useState('');
  const debouncedSearch = useDebouncedValue(searchInput, 300);
  useEffect(() => {
    if (debouncedSearch === params.search) return;
    setParams((prev) => ({ ...prev, search: debouncedSearch, page: 1 }));
  }, [debouncedSearch, params.search]);

  const q = usePages(params);
  const del = useDeletePage();
  const restore = useRestorePage();
  const forceDel = useForceDeletePage();
  const transition = useTransitionPage();

  const patch = (p: Partial<PagesListParams>) =>
    setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const onDelete = async (r: PageData) => {
    if (
      await confirm({
        title: t('page.confirm.deleteTitle'),
        text: t('page.confirm.deleteText', { title: r.title }),
        confirmText: t('page.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(r.id);
  };

  const onForceDelete = async (r: PageData) => {
    if (
      await confirm({
        title: t('page.confirm.forceTitle'),
        text: t('page.confirm.forceText', { title: r.title }),
        confirmText: t('page.confirm.forceYes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      forceDel.mutate(r.id);
  };

  const onPublish = async (r: PageData) => {
    if (
      await confirm({
        title: t('page.confirm.publishTitle'),
        text: t('page.confirm.publishText'),
        confirmText: t('page.action.publish'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      transition.mutate({ id: r.id, status: 'published' });
  };

  const onArchive = async (r: PageData) => {
    if (
      await confirm({
        title: t('page.confirm.archiveTitle'),
        text: t('page.confirm.archiveText'),
        confirmText: t('page.action.archive'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      transition.mutate({ id: r.id, status: 'archived' });
  };

  const inTrash = params.trashed === 'only';

  const columns: Column<PageData>[] = [
    {
      key: 'title',
      header: t('page.col.title'),
      render: (r) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{r.title}</p>
          <p className="truncate text-xs text-muted-foreground">/{r.slug}</p>
        </div>
      ),
    },
    {
      key: 'status',
      header: t('page.col.status'),
      render: (r) => (
        <Badge variant={STATUS_TONE[r.status]}>{t(`page.status.${r.status}`)}</Badge>
      ),
    },
    {
      key: 'locale',
      header: t('page.col.locale'),
      render: (r) => <Badge variant="muted">{r.locale.toUpperCase()}</Badge>,
    },
    {
      key: 'placement',
      header: t('page.col.placement'),
      render: (r) => (
        <div className="flex gap-1">
          {r.show_in_header ? (
            <Badge variant="muted" title={t('page.placement.header')}>
              {t('page.placement.headerShort')}
            </Badge>
          ) : null}
          {r.show_in_footer ? (
            <Badge variant="muted" title={t('page.placement.footer')}>
              {t('page.placement.footerShort')}
            </Badge>
          ) : null}
          {!r.show_in_header && !r.show_in_footer ? (
            <span className="text-xs text-muted-foreground">—</span>
          ) : null}
        </div>
      ),
    },
    {
      key: 'order',
      header: t('page.col.order'),
      align: 'center',
      render: (r) => (
        <span className="text-xs tabular-nums text-muted-foreground">{r.sort_order}</span>
      ),
    },
    {
      key: 'author',
      header: t('page.col.author'),
      render: (r) => (
        <span className="truncate text-xs text-muted-foreground">{r.author?.name ?? '—'}</span>
      ),
    },
    {
      key: 'date',
      header: t('page.col.date'),
      render: (r) => (
        <span className="whitespace-nowrap text-xs text-muted-foreground">
          {fmtDate(r.published_at ?? r.created_at, i18n.language)}
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
                    {t('page.action.restore')}
                  </DropdownMenuItem>
                ) : null}
                {canForceDelete ? (
                  <DropdownMenuItem
                    onClick={() => void onForceDelete(r)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('page.action.forceDelete')}
                  </DropdownMenuItem>
                ) : null}
              </>
            ) : (
              <>
                {canEdit ? (
                  <DropdownMenuItem
                    onClick={() => navigate(paths.pagesEdit.replace(':id', String(r.id)))}
                  >
                    <Pencil className="h-4 w-4" />
                    {t('page.action.edit')}
                  </DropdownMenuItem>
                ) : null}
                {canPublish && r.status !== 'published' ? (
                  <DropdownMenuItem onClick={() => void onPublish(r)}>
                    <Send className="h-4 w-4" />
                    {t('page.action.publish')}
                  </DropdownMenuItem>
                ) : null}
                {canArchive && r.status !== 'archived' ? (
                  <DropdownMenuItem onClick={() => void onArchive(r)}>
                    <Archive className="h-4 w-4" />
                    {t('page.action.archive')}
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
                      {t('page.action.delete')}
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
    searchInput ||
      params.status ||
      params.locale ||
      params.show_in_header ||
      params.show_in_footer ||
      params.trashed,
  );

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('page.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('page.subtitle')}</p>
        </div>
        {canCreate ? (
          <Button onClick={() => navigate(paths.pagesCreate)}>
            <Plus className="h-4 w-4" />
            {t('page.new')}
          </Button>
        ) : null}
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          placeholder={t('page.filter.search')}
          className="min-w-[200px] flex-1"
        />
        <select
          className={selectCls}
          value={params.status}
          onChange={(e) => patch({ status: e.target.value as PagesListParams['status'] })}
        >
          <option value="">{t('page.filter.statusAll')}</option>
          <option value="draft">{t('page.status.draft')}</option>
          <option value="published">{t('page.status.published')}</option>
          <option value="archived">{t('page.status.archived')}</option>
        </select>
        <select
          className={selectCls}
          value={params.locale}
          onChange={(e) => patch({ locale: e.target.value as PagesListParams['locale'] })}
        >
          <option value="">{t('page.filter.localeAll')}</option>
          <option value="ar">{t('articles.locale.ar')}</option>
          <option value="en">{t('articles.locale.en')}</option>
        </select>
        <select
          className={selectCls}
          value={params.show_in_header}
          onChange={(e) =>
            patch({ show_in_header: e.target.value as PagesListParams['show_in_header'] })
          }
        >
          <option value="">{t('page.filter.headerAll')}</option>
          <option value="1">{t('page.filter.headerYes')}</option>
          <option value="0">{t('page.filter.headerNo')}</option>
        </select>
        <select
          className={selectCls}
          value={params.show_in_footer}
          onChange={(e) =>
            patch({ show_in_footer: e.target.value as PagesListParams['show_in_footer'] })
          }
        >
          <option value="">{t('page.filter.footerAll')}</option>
          <option value="1">{t('page.filter.footerYes')}</option>
          <option value="0">{t('page.filter.footerNo')}</option>
        </select>
        <select
          className={selectCls}
          value={params.sort}
          onChange={(e) => patch({ sort: e.target.value as PagesListParams['sort'] })}
        >
          <option value="-created_at">{t('page.filter.sortNewest')}</option>
          <option value="-published_at">{t('page.filter.sortPublished')}</option>
          <option value="title">{t('page.filter.sortTitle')}</option>
          <option value="sort_order">{t('page.filter.sortOrder')}</option>
        </select>
        {canSeeTrash ? (
          <select
            className={selectCls}
            value={params.trashed}
            onChange={(e) => patch({ trashed: e.target.value as PagesListParams['trashed'] })}
          >
            <option value="">{t('page.filter.trashedNone')}</option>
            <option value="only">{t('page.filter.trashedOnly')}</option>
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
                locale: '',
                show_in_header: '',
                show_in_footer: '',
                sort: '-created_at',
                trashed: '',
              });
            }}
          >
            <X className="h-4 w-4" />
            {t('page.filter.reset')}
          </Button>
        ) : null}
      </div>

      <DataTable
        columns={columns}
        rows={q.data?.data ?? []}
        rowKey={(r) => r.id}
        loading={q.isLoading}
        emptyTitle={inTrash ? t('page.empty.trashTitle') : t('page.empty.title')}
        emptyDescription={inTrash ? t('page.empty.trashDescription') : t('page.empty.description')}
      />

      {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
    </div>
  );
}
